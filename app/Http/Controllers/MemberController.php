<?php

namespace App\Http\Controllers;

use App\Models\AccountType;
use App\Models\AuditLog;
use App\Models\Member;
use App\Models\MemberNominee;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AccountService;
use App\Services\NumberGenerator;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class MemberController extends Controller
{
    public function __construct(private AccountService $accounts, private SmsService $sms) {}

    public function index(Request $request)
    {
        $members = Member::search($request->q)->with('category', 'branch')->visibleTo($request->user(), $request->branch)
            ->when($request->channel, fn ($q, $c) => $q->where('onboarding_channel', $c))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->category, fn ($q, $c) => $q->where('member_category_id', $c))
            ->when($request->employer, fn ($q, $e) => $q->where('employer_id', $e))
            ->orderBy('member_no')->paginate(25)->withQueryString();

        if ($request->export === 'csv') {
            return $this->csv(Member::search($request->q)->when($request->status, fn ($q, $s) => $q->where('status', $s))->get());
        }
        return view('members.index', compact('members'));
    }

    public function create()
    {
        return view('members.form', ['member' => new Member(['joined_on' => today(), 'status' => 'pending'])] + $this->formLists());
    }

    private function formLists(): array
    {
        return ['branches' => \App\Models\Branch::where('is_active', true)->orderBy('name')->get(),
                'categories' => \App\Models\MemberCategory::orderBy('name')->get(),
                'employers' => \App\Models\Employer::where('is_active', true)->orderBy('name')->get()];
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $fee = (float) Setting::get('registration_fee', 0);

        $member = DB::transaction(function () use ($data, $request, $fee) {
            $member = Member::create($data + ['member_no' => NumberGenerator::memberNo()]);
            $this->accounts->openDefaultAccounts($member);

            if ($request->boolean('create_login')) {
                $user = User::create([
                    'name' => $member->full_name,
                    'email' => ($member->email && ! User::where('email', $member->email)->exists())
                        ? $member->email : strtolower($member->member_no) . '@members.local',
                    'phone' => $member->phone,
                    'role' => 'member',
                    'password' => Hash::make($member->national_id),
                ]);
                $member->update(['user_id' => $user->id]);
            }

            if ($fee > 0 && $request->boolean('fee_paid')) {
                Transaction::create([
                    'reference' => NumberGenerator::reference('RF'), 'member_id' => $member->id, 'type' => 'fee',
                    'direction' => 'credit', 'amount' => $fee, 'channel' => $request->input('fee_channel', 'cash'),
                    'txn_date' => today(), 'posted_by' => auth()->id(), 'description' => 'Registration fee',
                ]);
            }
            AuditLog::record('member_created', $member);
            return $member;
        });

        $this->sms->send($member->phone, "Welcome to " . config('sacco.name') . ", {$member->first_name}. Your member no. is {$member->member_no}."
            . ($member->user_id ? ' Portal login: your member no. & ID number as password.' : ''));

        return redirect()->route('members.show', $member)->with('success', "Member {$member->member_no} registered.");
    }

    public function show(Member $member)
    {
        $member->load(['accounts.type', 'nominees', 'user', 'documents', 'category', 'employerRecord',
            'fixedDeposits' => fn ($q) => $q->where('status', 'active'),
            'loans' => fn ($q) => $q->with('product')->latest(),
            'guarantees' => fn ($q) => $q->with('loan.member')->latest()]);
        $transactions = $member->transactions()->with('account.type')->latest('txn_date')->latest('id')->take(15)->get();
        return view('members.show', compact('member', 'transactions'));
    }

    public function edit(Member $member)
    {
        return view('members.form', compact('member') + $this->formLists());
    }

    public function update(Request $request, Member $member)
    {
        $member->update($this->validated($request, $member));
        $member->user?->update(['name' => $member->full_name, 'phone' => $member->phone]);
        AuditLog::record('member_updated', $member);
        return redirect()->route('members.show', $member)->with('success', 'Member updated.');
    }

    public function status(Request $request, Member $member)
    {
        $data = $request->validate(['status' => 'required|in:active,dormant,suspended,exited', 'reason' => 'nullable|string|max:255']);

        if ($data['status'] === 'exited') {
            if ($member->outstandingLoans() > 0) return back()->with('error', 'Member has outstanding loans. Clear or offset them first.');
            if ($member->committedGuarantees() > 0) return back()->with('error', 'Member is guaranteeing running loans. Substitute guarantors first.');
            $member->exited_on = today();
        }
        $member->status = $data['status'];
        $member->save();
        $member->user?->update(['is_active' => in_array($data['status'], ['active', 'dormant'])]);
        AuditLog::record('member_status', $member, $data);
        return back()->with('success', 'Status updated.');
    }

    public function statementPdf(Request $request, Member $member)
    {
        $member->load('accounts.type', 'loans.product');
        $from = $request->date('from') ?? now()->startOfYear();
        $to = $request->date('to') ?? today();
        $rows = $member->transactions()->with('account.type', 'loan')
            ->whereBetween('txn_date', [$from->toDateString(), $to->toDateString()])->orderBy('txn_date')->orderBy('id')->get();
        $data = compact('member', 'rows', 'from', 'to');
        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            return \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.statement', $data)->download("statement-{$member->member_no}.pdf");
        }
        return view('pdf.statement', $data);
    }

    public function statement(Request $request, Member $member)
    {
        $from = $request->date('from') ?? now()->startOfYear();
        $to = $request->date('to') ?? today();
        $member->load('accounts.type', 'loans.product');

        $rows = $member->transactions()->with('account.type', 'loan')
            ->whereBetween('txn_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('txn_date')->orderBy('id')->get();

        return view('members.statement', compact('member', 'rows', 'from', 'to'));
    }

    public function addNominee(Request $request, Member $member)
    {
        $data = $request->validate([
            'name' => 'required|string|max:150', 'relationship' => 'required|string|max:50',
            'national_id' => 'nullable|string|max:20', 'phone' => 'nullable|string|max:20',
            'percentage' => 'required|numeric|min:1|max:100',
        ]);
        if ($member->nominees()->sum('percentage') + $data['percentage'] > 100) {
            return back()->with('error', 'Nominee allocations cannot exceed 100%.');
        }
        $member->nominees()->create($data);
        return back()->with('success', 'Nominee added.');
    }

    public function removeNominee(MemberNominee $nominee)
    {
        $nominee->delete();
        return back()->with('success', 'Nominee removed.');
    }

    /** JSON search used by Select2-style pickers (deposits, guarantors). */
    public function lookup(Request $request)
    {
        return Member::search($request->q)->whereIn('status', ['active', 'dormant'])->take(15)->get()
            ->map(fn ($m) => [
                'id' => $m->id, 'text' => "{$m->member_no} - {$m->full_name}",
                'phone' => $m->phone, 'free_guarantee' => $m->freeGuaranteeCapacity(),
            ]);
    }

    /* ---------- Approval workflow ---------- */

    public function approve(Member $member)
    {
        if ($member->status !== 'pending') return back()->with('error', 'Member is not pending approval.');
        if ($member->documents()->where('type', 'national_id')->doesntExist() && setting('require_id_document', 1))
            return back()->with('error', 'Upload a copy of the National ID/passport before approval (KYC).');
        $member->update(['status' => 'active', 'approved_by' => auth()->id(), 'approved_at' => now(), 'branch_id' => $member->branch_id ?? auth()->user()->branch_id]);
        $member->user?->update(['is_active' => true]);
        AuditLog::record('member_approved', $member);
        app(\App\Services\WebhookService::class)->dispatch('member.approved', ['member_no' => $member->member_no, 'name' => $member->full_name]);
        $this->sms->send($member->phone, "Your membership with " . sacco_name() . " has been approved. Member no: {$member->member_no}.", $member->id);
        return back()->with('success', 'Member approved.');
    }

    /* ---------- Documents ---------- */

    public function uploadDocument(Request $request, Member $member)
    {
        $d = $request->validate(['type' => 'required|in:national_id,passport,photo,kra_pin,payslip,signature,application_form,other',
            'title' => 'nullable|string|max:120', 'file' => 'required|file|max:5120|mimes:pdf,jpg,jpeg,png']);
        $path = $request->file('file')->store("members/{$member->id}", 'local');
        $member->documents()->create(['type' => $d['type'], 'title' => $d['title'] ?: ucwords(str_replace('_', ' ', $d['type'])),
            'path' => $path, 'uploaded_by' => auth()->id()]);
        if ($d['type'] === 'photo') $member->update(['photo' => $path]);
        return back()->with('success', 'Document uploaded.');
    }

    public function document(\App\Models\MemberDocument $document)
    {
        $user = auth()->user();
        abort_unless($user->isStaff() || $user->member?->id === $document->member_id, 403);
        return \Illuminate\Support\Facades\Storage::disk('local')->response($document->path);
    }

    public function verifyDocument(\App\Models\MemberDocument $document)
    {
        $document->update(['verified' => true, 'verified_by' => auth()->id()]);
        return back()->with('success', 'Document verified.');
    }

    public function deleteDocument(\App\Models\MemberDocument $document)
    {
        \Illuminate\Support\Facades\Storage::disk('local')->delete($document->path);
        $document->delete();
        return back()->with('success', 'Document removed.');
    }

    /* ---------- Exit & clearance ---------- */

    public function exitForm(Member $member, \App\Services\MemberExitService $exit)
    {
        return view('members.exit', ['member' => $member, 'p' => $exit->preview($member)]);
    }

    public function processExit(Request $request, Member $member, \App\Services\MemberExitService $exit)
    {
        $d = $request->validate(['channel' => 'required|in:cash,bank,mpesa,cheque', 'reason' => 'required|string|max:255', 'reference' => 'nullable|string|max:60']);
        try {
            $exit->process($member, $d['channel'], $d['reason'], $d['reference'] ?? null);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        return redirect()->route('members.show', $member)->with('success', 'Member exited and refund posted.');
    }

    public function transferShares(Request $request, Member $member, \App\Services\MemberExitService $exit)
    {
        $d = $request->validate(['to_member_id' => 'required|exists:members,id', 'amount' => 'required|numeric|min:1']);
        if ((int) $d['to_member_id'] === $member->id) return back()->with('error', 'Choose a different member.');
        try {
            $exit->transferShares($member, Member::findOrFail($d['to_member_id']), (float) $d['amount']);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        return back()->with('success', 'Shares transferred.');
    }

    private function validated(Request $request, ?Member $member = null): array
    {
        $data = $request->validate([
            'member_category_id' => 'nullable|exists:member_categories,id',
            'employer_id' => 'nullable|exists:employers,id',
            'occupation' => 'nullable|string|max:120',
            'source_of_funds' => 'nullable|string|max:120',
            'risk_rating' => 'nullable|in:low,medium,high',
            'branch_id' => 'nullable|exists:branches,id',
        ]);
        if (! $request->user()->isHq()) unset($data['branch_id']); // branch staff register into their own branch
        $data['is_pep'] = $request->boolean('is_pep');
        if ($request->boolean('data_consent') && ! $member?->data_consent_at) $data['data_consent_at'] = now();
        if (! empty($data['employer_id']) && ! $request->filled('employer')) {
            $request->merge(['employer' => \App\Models\Employer::find($data['employer_id'])->name]);
        }
        $base = $this->baseValidated($request, $member);
        $base['monthly_contribution'] = $base['monthly_contribution'] ?? 0; // blank box = no fixed contribution
        return $data + $base;
    }

    private function baseValidated(Request $request, ?Member $member = null): array
    {
        return $request->validate([
            'first_name' => 'required|string|max:60',
            'middle_name' => 'nullable|string|max:60',
            'last_name' => 'required|string|max:60',
            'national_id' => ['required', 'string', 'max:20', Rule::unique('members')->ignore($member)],
            'kra_pin' => 'nullable|string|max:20',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:120',
            'gender' => 'nullable|in:male,female,other',
            'date_of_birth' => 'nullable|date|before:-18 years',
            'address' => 'nullable|string|max:255',
            'employer' => 'nullable|string|max:120',
            'payroll_no' => 'nullable|string|max:30',
            'department' => 'nullable|string|max:120',
            'monthly_contribution' => 'nullable|numeric|min:0',
            'joined_on' => 'required|date',
            'status' => $member ? 'sometimes' : 'required|in:pending,active',
        ]);
    }

    private function csv($members)
    {
        $types = AccountType::all();
        return response()->streamDownload(function () use ($members, $types) {
            $out = fopen('php://output', 'w');
            fputcsv($out, array_merge(['Member No', 'Name', 'ID No', 'Phone', 'Payroll', 'Status', 'Joined'], $types->pluck('name')->all(), ['Loan Balance']));
            foreach ($members as $m) {
                $m->load('accounts');
                fputcsv($out, array_merge(
                    [$m->member_no, $m->full_name, $m->national_id, $m->phone, $m->payroll_no, $m->status, $m->joined_on->toDateString()],
                    $types->map(fn ($t) => (float) optional($m->accounts->firstWhere('account_type_id', $t->id))->balance)->all(),
                    [$m->outstandingLoans()]
                ));
            }
            fclose($out);
        }, 'members-' . now()->format('Ymd') . '.csv');
    }
}
