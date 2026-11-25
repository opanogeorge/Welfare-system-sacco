<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\LoanGuarantor;
use App\Models\LoanProduct;
use App\Models\Member;
use App\Services\LoanCalculator;
use App\Services\LoanService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use RuntimeException;

class LoanController extends Controller
{
    public function __construct(private LoanService $loans) {}

    public function index(Request $request)
    {
        $loans = Loan::with('member', 'product')->visibleTo($request->user(), $request->branch)
            ->when($request->band, fn ($q, $b) => $q->where('risk_band', $b))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->product, fn ($q, $p) => $q->where('loan_product_id', $p))
            ->when($request->q, fn ($q, $s) => $q->where(fn ($w) => $w->where('loan_no', 'like', "%$s%")
                ->orWhereHas('member', fn ($m) => $m->search($s))))
            ->latest()->paginate(25)->withQueryString();

        $products = LoanProduct::orderBy('name')->get();
        return view('loans.index', compact('loans', 'products'));
    }

    public function create(Request $request)
    {
        $products = LoanProduct::where('is_active', true)->get();
        $member = $request->member ? Member::find($request->member) : null;
        $topUp = $request->top_up ? Loan::where('status', 'disbursed')->find($request->top_up) : null;
        if ($topUp) $member = $topUp->member;
        return view('loans.create', compact('products', 'member', 'topUp'));
    }

    public function eligibility(Request $request)
    {
        $member = Member::findOrFail($request->member_id);
        $product = LoanProduct::findOrFail($request->product_id);
        $topUp = $request->top_up_of ? Loan::find($request->top_up_of) : null;
        return response()->json($this->loans->eligibility($member, $product, $topUp) + [
            'min_guarantors' => $product->min_guarantors,
            'min_period' => $product->min_period,
            'max_period' => $product->max_period,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'member_id' => 'required|exists:members,id',
            'loan_product_id' => 'required|exists:loan_products,id',
            'amount' => 'required|numeric|min:1',
            'period' => 'required|integer|min:1|max:120',
            'purpose' => 'nullable|string|max:255',
            'guarantors' => 'array',
            'guarantors.*.member_id' => 'required|exists:members,id|distinct',
            'guarantors.*.amount' => 'required|numeric|min:1',
            'top_up_of' => 'nullable|exists:loans,id',
            'refinance' => 'nullable|boolean',
        ]);

        try {
            $loan = $this->loans->apply(
                Member::findOrFail($data['member_id']),
                LoanProduct::findOrFail($data['loan_product_id']),
                (float) $data['amount'], (int) $data['period'], $data['purpose'] ?? null,
                $data['guarantors'] ?? [],
                ! empty($data['top_up_of']) ? Loan::find($data['top_up_of']) : null
            );
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
        if ($request->boolean('refinance') && $loan->top_up_of) $loan->forceFill(['is_refinance' => true])->saveQuietly();
        return redirect()->route('loans.show', $loan)->with('success', "Loan {$loan->loan_no} captured.");
    }

    public function show(Loan $loan)
    {
        $loan->load('member.accounts.type', 'product', 'guarantors.member', 'schedules', 'repayments.poster', 'approver', 'approvals.user', 'collaterals', 'topUpOf');
        $preview = $loan->status !== 'disbursed' && $loan->schedules->isEmpty()
            ? LoanCalculator::schedule((float) ($loan->amount_approved ?? $loan->amount_applied), (float) $loan->interest_rate, $loan->period, $loan->interest_method)
            : null;
        $eligibility = in_array($loan->status, ['pending', 'appraised', 'guarantors_pending'])
            ? $this->loans->eligibility($loan->member, $loan->product, $loan->topUpOf) : null;
        $score = in_array($loan->status, ['pending', 'appraised', 'guarantors_pending', 'approved']) ? $this->loans->creditScore($loan->member) : null;
        return view('loans.show', compact('loan', 'preview', 'eligibility', 'score'));
    }

    public function addCollateral(Request $request, Loan $loan)
    {
        $d = $request->validate(['type' => 'required|string|max:40', 'description' => 'required|string|max:255',
            'value' => 'required|numeric|min:0', 'reference' => 'nullable|string|max:120', 'document' => 'nullable|file|max:5120|mimes:pdf,jpg,jpeg,png']);
        if ($request->hasFile('document')) $d['document'] = $request->file('document')->store("collateral/{$loan->id}", 'local');
        $loan->collaterals()->create($d);
        return back()->with('success', 'Collateral recorded.');
    }

    public function releaseCollateral(\App\Models\LoanCollateral $collateral)
    {
        if ($collateral->loan->status === 'disbursed') return back()->with('error', 'Collateral can only be released once the loan is cleared.');
        $collateral->update(['status' => 'released']);
        return back()->with('success', 'Collateral released.');
    }

    public function collateralDocument(\App\Models\LoanCollateral $collateral)
    {
        abort_unless($collateral->document, 404);
        return \Illuminate\Support\Facades\Storage::disk('local')->download($collateral->document);
    }

    public function reschedule(Request $request, Loan $loan)
    {
        $d = $request->validate(['period' => 'required|integer|min:1|max:120', 'first_due_date' => 'required|date|after:today',
            'interest_rate' => 'nullable|numeric|min:0|max:100', 'reason' => 'required|string|max:255']);
        return $this->run(fn () => $this->loans->reschedule($loan, $request->user(), (int) $d['period'], Carbon::parse($d['first_due_date']),
            isset($d['interest_rate']) ? (float) $d['interest_rate'] : null, $d['reason']), 'Loan rescheduled.');
    }

    public function replaceGuarantor(Request $request, LoanGuarantor $guarantor)
    {
        $d = $request->validate(['member_id' => 'required|exists:members,id', 'amount' => 'required|numeric|min:1']);
        return $this->run(fn () => $this->loans->replaceGuarantor($guarantor, Member::findOrFail($d['member_id']), (float) $d['amount']), 'Guarantor replaced; the new guarantor has been asked to consent.');
    }

    public function releaseGuarantor(LoanGuarantor $guarantor)
    {
        return $this->run(fn () => $this->loans->releaseGuarantor($guarantor, request()->user(), request('reason') ?: 'Released by the SACCO'), 'Guarantor released and notified.');
    }

    /** Printable loan statement. */
    public function statement(Loan $loan)
    {
        $loan->load('member', 'product', 'schedules', 'repayments');
        return view('loans.statement', compact('loan'));
    }

    public function appraise(Request $request, Loan $loan)
    {
        $d = $request->validate(['remarks' => 'nullable|string|max:1000', 'gross_pay' => 'nullable|numeric|min:0',
            'net_pay' => 'nullable|numeric|min:0', 'other_deductions' => 'nullable|numeric|min:0', 'override' => 'nullable|boolean']);
        if ($request->boolean('override') && ! $request->filled('remarks')) return back()->withInput()->with('error', 'Give a reason in the remarks when overriding the affordability test.');
        return $this->run(fn () => $this->loans->appraise($loan, $request->user(), $d['remarks'] ?? null, $d, $request->boolean('override')), 'Loan appraised and forwarded for approval.');
    }

    /** AJAX: live affordability check on the appraisal form. */
    public function affordability(Request $request, Loan $loan)
    {
        $request->validate(['gross_pay' => 'required|numeric|min:1', 'other_deductions' => 'nullable|numeric|min:0']);
        return $this->loans->affordability($loan, (float) $request->gross_pay, (float) $request->input('other_deductions', 0));
    }

    public function fine(Request $request, Loan $loan)
    {
        $d = $request->validate(['amount' => 'required|numeric|min:1', 'category' => 'required|in:late_penalty,bounced_cheque,legal_fees,collection_costs,other', 'reason' => 'required|string|max:255']);
        return $this->run(fn () => $this->loans->chargeFine($loan, (float) $d['amount'], $d['category'], $d['reason'], $request->user()), 'Fine charged.');
    }

    public function waive(Request $request, Loan $loan)
    {
        $d = $request->validate(['amount' => 'required|numeric|min:1', 'what' => 'required|in:penalty,interest,both', 'reason' => 'required|string|max:255']);
        try {
            $w = $this->loans->waive($loan, (float) $d['amount'], $d['what'], $d['reason'], $request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        return back()->with('success', 'Waived KES ' . number_format($w, 2) . '.');
    }

    public function recoveryAction(Request $request, Loan $loan)
    {
        $d = $request->validate(['action' => 'required|in:' . implode(',', array_keys(LoanService::RECOVERY_ACTIONS)), 'notes' => 'nullable|string|max:2000',
            'promise_date' => 'nullable|required_if:action,promise_to_pay|date|after_or_equal:today', 'promise_amount' => 'nullable|required_if:action,promise_to_pay|numeric|min:1']);
        $this->loans->addRecoveryAction($loan, $d['action'], $d['notes'] ?? null, $request->user(), $d['promise_date'] ?? null, isset($d['promise_amount']) ? (float) $d['promise_amount'] : null);
        return back()->with('success', 'Recovery action recorded.');
    }

    public function demandLetter(Loan $loan)
    {
        $loan->load('member', 'product', 'guarantors.member');
        if (! $loan->recoveryActions()->where('action', 'demand_letter')->where('created_at', '>=', now()->subDay())->exists()) {
            $this->loans->addRecoveryAction($loan, 'demand_letter', 'Demand letter generated', request()->user());
        }
        return view('loans.demand-letter', ['loan' => $loan, 'days' => (int) setting('demand_notice_days', 14)]);
    }

    public function writeOff(Request $request, Loan $loan)
    {
        $request->validate(['reason' => 'required|string|max:255']);
        return $this->run(fn () => $this->loans->writeOff($loan, $request->user(), $request->reason), 'Loan written off. Later payments will be posted as bad debts recovered.');
    }

    /** Loans in recovery: arrears, last action, open promises. */
    public function recovery(Request $request)
    {
        $loans = Loan::with(['member', 'product', 'recoveryActions' => fn ($q) => $q->latest()->limit(1)])
            ->visibleTo($request->user(), $request->branch)->whereIn('status', ['disbursed', 'written_off'])->get()
            ->map(fn ($l) => ['loan' => $l, 'days' => $l->status === 'disbursed' ? $l->daysInArrears() : 999, 'arrears' => $l->status === 'disbursed' ? $l->arrears() : $l->outstanding,
                              'last' => $l->recoveryActions->first(),
                              'promise' => $l->recoveryActions()->where('promise_status', 'open')->latest()->first()])
            ->filter(fn ($r) => $r['days'] > 0 && ($r['loan']->status === 'written_off' ? $r['loan']->outstanding > 0 : true))
            ->when($request->stage, fn ($c, $s) => $c->filter(fn ($r) => ($r['loan']->recovery_stage ?? 'reminder') === $s))
            ->sortByDesc('days')->values();
        $writtenOff = ['count' => Loan::where('status', 'written_off')->count(), 'amount' => (float) Loan::where('status', 'written_off')->sum('written_off_amount'),
                       'recovered' => (float) Loan::where('status', 'written_off')->sum('recovered_after_write_off')];
        return view('loans.recovery', compact('loans', 'writtenOff'));
    }

    public function approve(Request $request, Loan $loan)
    {
        $request->validate(['amount_approved' => 'required|numeric|min:1']);
        return $this->run(fn () => $this->loans->approve($loan, $request->user(), (float) $request->amount_approved, $request->input('remarks')), 'Loan approved.');
    }

    public function reject(Request $request, Loan $loan)
    {
        $request->validate(['reason' => 'required|string|max:255']);
        return $this->run(fn () => $this->loans->reject($loan, $request->user(), $request->reason), 'Loan rejected.');
    }

    public function disburse(Request $request, Loan $loan)
    {
        $d = $request->validate([
            'channel' => 'required|in:savings,bank,mpesa,cheque,opening',
            'disbursed_on' => 'required|date|before_or_equal:today',
            'first_due_date' => 'required|date|after:disbursed_on',
            'external_ref' => 'nullable|string|max:60',
        ]);
        return $this->run(fn () => $this->loans->disburse($loan, $request->user(), $d['channel'],
            Carbon::parse($d['disbursed_on']), Carbon::parse($d['first_due_date']), $d['external_ref'] ?? null), 'Loan disbursed.');
    }

    public function repay(Request $request, Loan $loan)
    {
        $d = $request->validate([
            'amount' => 'required|numeric|min:1',
            'channel' => 'required|in:cash,mpesa,bank,payroll,cheque',
            'external_ref' => 'nullable|string|max:60',
            'paid_on' => 'required|date|before_or_equal:today',
        ]);
        return $this->run(fn () => $this->loans->repay($loan, (float) $d['amount'], $d['channel'], $d['external_ref'] ?? null, Carbon::parse($d['paid_on'])), 'Repayment posted.');
    }

    public function recover(Request $request, Loan $loan)
    {
        try {
            $amt = $this->loans->recoverFromGuarantors($loan, $request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        return back()->with('success', 'Recovered KES ' . number_format($amt, 2) . ' from guarantors.');
    }

    /** Staff can record a guarantor's response captured on a paper form. */
    public function guarantorRespond(Request $request, LoanGuarantor $guarantor)
    {
        return $this->run(fn () => $this->loans->respondGuarantee($guarantor, $request->boolean('accept'), 'paper', $request->input('reason')), 'Guarantor response recorded.');
    }

    public function calculator(Request $request)
    {
        $products = LoanProduct::where('is_active', true)->get();
        $result = null;
        if ($request->filled(['amount', 'period', 'rate'])) {
            $result = LoanCalculator::schedule((float) $request->amount, (float) $request->rate, (int) $request->period, $request->input('method', 'reducing'));
        }
        return view('loans.calculator', compact('products', 'result'));
    }

    private function run(callable $fn, string $msg)
    {
        try {
            $fn();
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
        return back()->with('success', $msg);
    }
}
