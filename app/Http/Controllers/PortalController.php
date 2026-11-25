<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\LoanGuarantor;
use App\Models\LoanProduct;
use App\Models\Member;
use App\Services\LoanService;
use Illuminate\Http\Request;
use RuntimeException;

class PortalController extends Controller
{
    public function __construct(private LoanService $loans) {}

    private function member(): Member
    {
        $m = auth()->user()->member;
        abort_unless($m, 403, 'No member record is linked to this login.');
        return $m;
    }

    public function home()
    {
        $member = $this->member()->load('accounts.type');
        $loans = $member->loans()->with('product')->where('status', 'disbursed')->get();
        $pendingGuarantees = $member->guarantees()->where('status', 'pending')->count();
        $recent = $member->transactions()->with('account.type')->latest('txn_date')->latest('id')->take(8)->get();
        $nextDue = $loans->map(fn ($l) => $l->schedules()->whereIn('status', ['pending', 'partial', 'overdue'])->orderBy('due_date')->first())->filter()->sortBy('due_date')->first();
        return view('portal.home', compact('member', 'loans', 'pendingGuarantees', 'recent', 'nextDue'));
    }

    public function statement(Request $request)
    {
        $member = $this->member()->load('accounts.type');
        $from = $request->date('from') ?? now()->startOfYear();
        $to = $request->date('to') ?? today();
        $rows = $member->transactions()->with('account.type', 'loan')
            ->whereBetween('txn_date', [$from->toDateString(), $to->toDateString()])->orderBy('txn_date')->orderBy('id')->get();
        return view('members.statement', compact('member', 'rows', 'from', 'to') + ['portal' => true]);
    }

    public function loans()
    {
        $member = $this->member();
        $loans = $member->loans()->with('product')->latest()->get();
        return view('portal.loans', compact('member', 'loans'));
    }

    public function loan(Loan $loan)
    {
        abort_unless($loan->member_id === $this->member()->id, 404);
        $loan->load('product', 'schedules', 'repayments', 'guarantors.member');
        return view('portal.loan', compact('loan'));
    }

    public function applyForm()
    {
        $member = $this->member();
        $products = LoanProduct::where('is_active', true)->get()->map(fn ($p) => ['product' => $p, 'eligibility' => $this->loans->eligibility($member, $p)]);
        return view('portal.apply', compact('member', 'products'));
    }

    public function apply(Request $request)
    {
        $data = $request->validate([
            'loan_product_id' => 'required|exists:loan_products,id',
            'amount' => 'required|numeric|min:1',
            'period' => 'required|integer|min:1|max:120',
            'purpose' => 'required|string|max:255',
            'guarantors' => 'array',
            'guarantors.*.member_no' => 'required|exists:members,member_no|distinct',
            'guarantors.*.amount' => 'required|numeric|min:1',
        ]);
        $guarantors = collect($data['guarantors'] ?? [])->map(fn ($g) => [
            'member_id' => Member::where('member_no', $g['member_no'])->value('id'), 'amount' => $g['amount'],
        ])->all();

        try {
            $loan = $this->loans->apply($this->member(), LoanProduct::findOrFail($data['loan_product_id']),
                (float) $data['amount'], (int) $data['period'], $data['purpose'], $guarantors);
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
        return redirect()->route('portal.loans.show', $loan)->with('success', 'Application submitted. Your guarantors have been notified by SMS.');
    }

    public function guarantees()
    {
        $member = $this->member();
        $all = $member->guarantees()->with('loan.member', 'loan.product')->latest()->get();
        $requests = $all->where('status', 'pending');
        $obligations = $all->where('status', 'accepted')->filter(fn ($g) => in_array($g->loan->status, ['pending', 'appraised', 'approved', 'disbursed']))
            ->map(fn ($g) => ['g' => $g, 'exposure' => $g->exposure(), 'days' => $g->loan->status === 'disbursed' ? $g->loan->daysInArrears() : 0]);
        $history = $all->whereIn('status', ['declined', 'released', 'expired', 'withdrawn'])->take(20);
        $totals = ['exposure' => $obligations->sum('exposure'), 'guaranteed' => $obligations->sum(fn ($o) => (float) $o['g']->amount),
                   'capacity' => $member->freeGuaranteeCapacity(), 'at_risk' => $obligations->where('days', '>', 30)->sum('exposure')];
        return view('portal.guarantees', compact('member', 'requests', 'obligations', 'history', 'totals'));
    }

    /** Month-by-month contributions vs the member's committed monthly amount. */
    public function contributions(Request $request)
    {
        $member = $this->member()->load('accounts.type');
        $months = (int) $request->input('months', 12);
        $start = now()->subMonthsNoOverflow($months - 1)->startOfMonth();
        if ($member->joined_on->gt($start)) $start = $member->joined_on->copy()->startOfMonth();

        $rows = \App\Models\Transaction::with('account.type')->where('member_id', $member->id)
            ->whereIn('type', ['deposit', 'transfer_in'])->where('direction', 'credit')->whereNotNull('member_account_id')
            ->whereDate('txn_date', '>=', $start)->orderBy('txn_date')->get();

        $types = $member->accounts->map(fn ($a) => $a->type)->unique('id')->values();
        $table = [];
        for ($d = $start->copy(); $d->lte(now()); $d->addMonthNoOverflow()) {
            $key = $d->format('Y-m');
            $inMonth = $rows->filter(fn ($t) => $t->txn_date->format('Y-m') === $key);
            $byType = $types->mapWithKeys(fn ($t) => [$t->code => (float) $inMonth->filter(fn ($x) => $x->account?->type?->id === $t->id)->sum('amount')]);
            $table[] = ['month' => $d->copy(), 'by_type' => $byType, 'total' => $byType->sum(),
                        'channels' => $inMonth->pluck('channel')->unique()->implode(', '), 'expected' => (float) $member->monthly_contribution];
        }
        $table = array_reverse($table);
        $dep = collect($table)->sum(fn ($r) => $r['by_type']['DEP'] ?? 0);
        $expected = (float) $member->monthly_contribution * count($table);
        $summary = [
            'expected' => $expected, 'deposits' => $dep, 'arrears' => max(0, $expected - $dep),
            'months_paid' => collect($table)->filter(fn ($r) => ($r['by_type']['DEP'] ?? 0) >= $member->monthly_contribution && $member->monthly_contribution > 0)->count(),
            'shares' => $member->balance('SHR'), 'min_shares' => (float) setting('min_share_capital', 0),
        ];
        return view('portal.contributions', compact('member', 'table', 'types', 'summary', 'months'));
    }

    public function respond(Request $request, LoanGuarantor $guarantor)
    {
        abort_unless($guarantor->member_id === $this->member()->id, 404);
        try {
            $request->validate(['reason' => 'nullable|string|max:255']);
            $this->loans->respondGuarantee($guarantor, $request->boolean('accept'), 'portal', $request->input('reason'));
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        return back()->with('success', 'Response recorded.');
    }

    /* ---------- Withdrawals ---------- */

    public function withdrawals()
    {
        $member = $this->member()->load('accounts.type');
        $requests = $member->withdrawalRequests()->latest()->take(20)->get();
        return view('portal.withdrawals', compact('member', 'requests'));
    }

    public function requestWithdrawal(Request $request)
    {
        $member = $this->member();
        $d = $request->validate(['account_code' => 'required|exists:account_types,code', 'amount' => 'required|numeric|min:1',
            'channel' => 'required|in:mpesa,bank,cash', 'destination' => 'nullable|string|max:100', 'reason' => 'nullable|string|max:255']);
        $acc = $member->account($d['account_code']);
        if (! $acc || ! $acc->type->withdrawable) return back()->with('error', 'That account is not withdrawable.');
        if ($d['amount'] + (float) $acc->type->withdrawal_fee > $acc->availableBalance()) return back()->with('error', 'Insufficient available balance.');
        if ($member->withdrawalRequests()->where('status', 'pending')->exists()) return back()->with('error', 'You already have a pending request.');
        $member->withdrawalRequests()->create($d + ['destination' => $d['destination'] ?? $member->phone]);
        return back()->with('success', 'Withdrawal request submitted. You will get an SMS once it is processed.');
    }

    /* ---------- Profile & documents ---------- */

    public function profile()
    {
        $member = $this->member()->load('nominees', 'documents');
        $pending = \App\Models\MemberChangeRequest::where('member_id', $member->id)->where('status', 'pending')->latest()->first();
        $logins = \App\Models\AuditLog::where('user_id', auth()->id())->whereIn('action', ['login', 'login_failed', 'otp_failed'])->latest()->take(8)->get();
        return view('portal.profile', compact('member', 'pending', 'logins'));
    }

    public function requestChange(Request $request)
    {
        $member = $this->member();
        $d = $request->validate(['phone' => 'nullable|string|max:20', 'email' => 'nullable|email|max:120', 'address' => 'nullable|string|max:255',
            'employer' => 'nullable|string|max:120', 'department' => 'nullable|string|max:120', 'payroll_no' => 'nullable|string|max:30',
            'kra_pin' => 'nullable|string|max:20', 'occupation' => 'nullable|string|max:120']);
        $changes = array_filter($d, fn ($v, $k) => $v !== null && $v !== '' && $v != $member->$k, ARRAY_FILTER_USE_BOTH);
        if (! $changes) return back()->with('warning', 'No changes to submit.');
        \App\Models\MemberChangeRequest::create(['member_id' => $member->id, 'changes' => $changes]);
        return back()->with('success', 'Your update has been sent to the SACCO office for verification.');
    }

    /* ---------- Dividends ---------- */

    public function dividends()
    {
        $member = $this->member();
        $payouts = $member->dividendPayouts()->with('period')->whereHas('period', fn ($q) => $q->whereIn('status', ['approved', 'paid']))->latest()->get();
        return view('portal.dividends', compact('member', 'payouts'));
    }

    /* ---------- Complaints & inquiries ---------- */

    public function tickets()
    {
        $member = $this->member();
        return view('portal.tickets', ['member' => $member, 'tickets' => $member->tickets()->latest()->get()]);
    }

    public function storeTicket(Request $request)
    {
        $member = $this->member();
        $d = $request->validate(['category' => 'required|in:complaint,inquiry,suggestion', 'subject' => 'required|string|max:150', 'message' => 'required|string|max:5000']);
        $t = $member->tickets()->create($d + ['ticket_no' => 'TK' . now()->format('ymd') . str_pad((string) (\App\Models\Ticket::count() + 1), 4, '0', STR_PAD_LEFT)]);
        return back()->with('success', "Submitted. Your reference is {$t->ticket_no}.");
    }

    /* ---------- PDF statement ---------- */

    public function statementPdf(Request $request)
    {
        $member = $this->member()->load('accounts.type', 'loans.product');
        $from = $request->date('from') ?? now()->startOfYear();
        $to = $request->date('to') ?? today();
        $rows = $member->transactions()->with('account.type', 'loan')
            ->whereBetween('txn_date', [$from->toDateString(), $to->toDateString()])->orderBy('txn_date')->orderBy('id')->get();
        $data = compact('member', 'rows', 'from', 'to');

        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            return \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.statement', $data)->download("statement-{$member->member_no}.pdf");
        }
        return view('pdf.statement', $data); // browser print fallback
    }

    /* ---------- Welfare ---------- */

    public function welfare()
    {
        $member = $this->member()->load('accounts.type');
        $open = \App\Models\WelfareEvent::with('member')->where('status', 'open')->where('member_id', '!=', $member->id)->latest()->get();
        $mine = \App\Models\WelfareContribution::with('event')->where('member_id', $member->id)->latest()->take(50)->get();
        $dues = $mine->where('status', 'due');
        $myEvents = \App\Models\WelfareEvent::where('member_id', $member->id)->latest()->get();
        return view('portal.welfare', compact('member', 'open', 'mine', 'dues', 'myEvents'));
    }

    public function welfareGive(Request $request, \App\Models\WelfareEvent $event, \App\Services\WelfareService $svc)
    {
        $d = $request->validate(['amount' => 'required|numeric|min:10|max:500000']);
        try { $svc->giveFromSavings($this->member(), $event, (float) $d['amount']); }
        catch (\RuntimeException $e) { return back()->with('error', $e->getMessage()); }
        return back()->with('success', 'Thank you! KES ' . number_format($d['amount'], 2) . " has been sent from your savings to support {$event->member->first_name}.");
    }

    public function welfareRequest(Request $request, \App\Services\WelfareService $svc)
    {
        abort_unless((int) setting('welfare_requests_from_portal', 1) === 1, 403);
        $d = $request->validate(['type' => 'required|in:' . implode(',', array_keys(\App\Models\WelfareEvent::TYPES)),
            'relationship' => 'nullable|in:' . implode(',', array_keys(\App\Models\WelfareEvent::RELATIONSHIPS)),
            'details' => 'required|string|max:2000', 'event_date' => 'nullable|date', 'document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120']);
        $member = $this->member();
        $data = ['member_id' => $member->id, 'type' => $d['type'], 'relationship' => $d['relationship'] ?? null, 'details' => $d['details'],
            'event_date' => $d['event_date'] ?? null, 'title' => \App\Models\WelfareEvent::TYPES[$d['type']] . ' - ' . $member->full_name,
            'mode' => 'voluntary', 'levy_source' => 'none'];
        if ($request->hasFile('document')) $data['document'] = $request->file('document')->store('welfare', 'local');
        $svc->create($data, null, 'requested');
        return back()->with('success', 'Your request has been sent to the welfare committee. We will contact you shortly.');
    }

    /* ---------- Deposit / pay via M-Pesa ---------- */

    public function pay(Request $request)
    {
        $member = $this->member()->load('accounts.type');
        $loans = $member->loans()->whereIn('status', ['disbursed', 'written_off'])->get();
        $refs = $loans->pluck('loan_no')->push($member->member_no);
        $recent = \App\Models\MpesaTransaction::where(function ($q) use ($member, $refs) {
            $q->where('bill_ref', 'like', $member->member_no . '%')->orWhereIn('bill_ref', $refs)
              ->orWhere('msisdn', app(\App\Services\SmsService::class)->normalize($member->phone));
        })->latest()->take(10)->get();
        $stk = (bool) (config('sacco.mpesa.consumer_key') && config('sacco.mpesa.passkey'));
        $welfare = \App\Models\WelfareEvent::with('member')->where('status', 'open')->where('member_id', '!=', $member->id)->get();
        $welfareDue = (float) \App\Models\WelfareContribution::where('member_id', $member->id)->where('status', 'due')->sum('amount');
        return view('portal.pay', compact('member', 'loans', 'recent', 'stk', 'welfare', 'welfareDue') + ['preselect' => $request->query('to')]);
    }

    public function payPush(Request $request, \App\Services\MpesaStkService $stk)
    {
        $member = $this->member();
        $d = $request->validate(['to' => 'required|string|max:20', 'amount' => 'required|integer|min:10|max:250000', 'phone' => 'required|string|max:15']);

        if (preg_match('/^W(\d+)$/', $d['to'], $w) || $d['to'] === 'WLF') {
            $event = isset($w[1]) ? \App\Models\WelfareEvent::where('status', 'open')->find($w[1]) : null;
            if (isset($w[1]) && ! $event) return response()->json(['message' => 'This welfare event is closed.'], 422);
            $ref = $member->member_no . '-' . $d['to'];
            $label = $event ? "welfare contribution ({$event->title})" : 'welfare dues';
        } elseif (str_starts_with($d['to'], 'LN')) {
            $loan = $member->loans()->where('loan_no', $d['to'])->whereIn('status', ['disbursed', 'written_off'])->first();
            if (! $loan) return response()->json(['message' => 'Loan not found.'], 422);
            $ref = $loan->loan_no; $label = "loan {$loan->loan_no}";
        } else {
            $acc = $member->account($d['to']);
            if (! $acc) return response()->json(['message' => 'Account not found.'], 422);
            $ref = $d['to'] === 'DEP' ? $member->member_no : $member->member_no . '-' . $d['to'];
            $label = $acc->type->name;
        }
        try {
            $res = $stk->push($d['phone'], (float) $d['amount'], $ref, 'Sacco ' . $d['to']);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        \App\Models\AuditLog::record('member_stk_push', $member, ['to' => $d['to'], 'amount' => $d['amount']]);
        if (! empty($res['CheckoutRequestID'])) \Illuminate\Support\Facades\Cache::put("stk_owner_{$res['CheckoutRequestID']}", $member->id, now()->addHours(2));
        return response()->json(['checkout' => $res['CheckoutRequestID'] ?? null,
            'message' => "Check your phone and enter your M-Pesa PIN to pay KES " . number_format($d['amount']) . " to your $label."]);
    }

    public function payStatus(string $checkout)
    {
        abort_unless(\Illuminate\Support\Facades\Cache::get("stk_owner_$checkout") === $this->member()->id, 404);
        return response()->json(\Illuminate\Support\Facades\Cache::get("stk_result_$checkout", ['status' => 'pending']));
    }

    public function loanStatement(Loan $loan)
    {
        abort_unless($loan->member_id === $this->member()->id, 404);
        $loan->load('member', 'product', 'schedules', 'repayments', 'charges');
        return view('loans.statement', compact('loan'));
    }

    /** Lets a member confirm a guarantor's name by member number before submitting. */
    /**
     * Borrower checks a guarantor by member number. Returns the name and whether they can cover the amount,
     * without revealing the guarantor's deposits.
     */
    public function lookup(Request $request)
    {
        $m = Member::where('member_no', strtoupper((string) $request->member_no))->active()->first();
        if (! $m) return response()->json(['name' => null]);
        $out = ['name' => $m->full_name];
        if ($request->filled('amount')) {
            $e = app(\App\Services\GuarantorService::class)->eligibility($m, $this->member(), (float) $request->amount);
            $out['eligible'] = $e['eligible'];
            $out['message'] = $e['eligible'] ? 'Can guarantee this amount' : ($m->id === $this->member()->id ? 'You cannot guarantee your own loan'
                : 'Cannot guarantee this amount. Ask them to check their guarantor limit, or try a lower amount.');
        }
        return response()->json($out);
    }

    /* ---------- Guarantor self-service ---------- */

    public function guarantorRegister(\App\Services\GuarantorService $svc)
    {
        $svc->register($this->member());
        return back()->with('success', 'You are now registered as a guarantor. Other members can ask you to guarantee their loans.');
    }

    public function guaranteeRelease(Request $request, LoanGuarantor $guarantor, \App\Services\GuarantorService $svc)
    {
        abort_unless($guarantor->member_id === $this->member()->id, 404);
        $d = $request->validate(['reason' => 'required|string|max:255']);
        try { $svc->requestRelease($guarantor, $d['reason']); } catch (RuntimeException $e) { return back()->with('error', $e->getMessage()); }
        return back()->with('success', 'Release request sent. The borrower has been asked to find a replacement; the SACCO will confirm once approved.');
    }

    /** Borrower adds or replaces a guarantor on their own loan (e.g. after a decline or expiry). */
    public function addGuarantor(Request $request, Loan $loan)
    {
        $member = $this->member();
        abort_unless($loan->member_id === $member->id, 404);
        $d = $request->validate(['member_no' => 'required|string', 'amount' => 'required|numeric|min:1', 'replace' => 'nullable|integer']);
        $g = Member::where('member_no', strtoupper($d['member_no']))->first();
        if (! $g) return back()->withInput()->with('error', 'No member with that number.');
        try {
            $old = ! empty($d['replace']) ? $loan->guarantors()->findOrFail($d['replace']) : null;
            $old ? $this->loans->replaceGuarantor($old, $g, (float) $d['amount'])
                 : app(\App\Services\GuarantorService::class)->request($loan, $g, (float) $d['amount']);
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
        return back()->with('success', "{$g->full_name} has been sent an SMS to accept your guarantee request.");
    }
}
