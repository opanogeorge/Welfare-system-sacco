<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\LoanGuarantor;
use App\Models\Member;
use App\Services\GuarantorService;
use Illuminate\Http\Request;
use RuntimeException;

class GuarantorController extends Controller
{
    public function __construct(private GuarantorService $svc) {}

    /** Tracking: every guarantee, with filters; queues for consents and release requests. */
    public function index(Request $request)
    {
        $tab = $request->input('tab', 'guarantees');
        $base = LoanGuarantor::with('member', 'loan.member', 'loan.product');

        $guarantees = (clone $base)
            ->when($request->status, fn ($q, $s) => $q->where('status', $s), fn ($q) => $q->whereIn('status', ['pending', 'accepted']))
            ->when($request->q, fn ($q, $s) => $q->where(fn ($w) => $w
                ->whereHas('member', fn ($m) => $m->where('member_no', 'like', "%$s%")->orWhere('first_name', 'like', "%$s%")->orWhere('last_name', 'like', "%$s%"))
                ->orWhereHas('loan', fn ($l) => $l->where('loan_no', 'like', "%$s%")
                    ->orWhereHas('member', fn ($m) => $m->where('member_no', 'like', "%$s%")->orWhere('last_name', 'like', "%$s%")))))
            ->when($request->loan_status, fn ($q, $s) => $q->whereHas('loan', fn ($l) => $l->where('status', $s)))
            ->latest()->paginate(40, ['*'], 'gp')->withQueryString();
        if ($request->boolean('arrears')) {
            $guarantees->setCollection($guarantees->getCollection()->filter(fn ($g) => $g->loan->status === 'disbursed' && $g->loan->daysInArrears() > 0)->values());
        }

        $releaseRequests = (clone $base)->whereNotNull('release_requested_at')->where('status', 'accepted')->oldest('release_requested_at')->get();
        $pendingConsents = (clone $base)->where('status', 'pending')->orderBy('expires_at')->get();

        $register = Member::active()->when($request->rq, fn ($q, $s) => $q->where(fn ($w) => $w->where('member_no', 'like', "%$s%")->orWhere('first_name', 'like', "%$s%")->orWhere('last_name', 'like', "%$s%")))
            ->when($request->gstatus, fn ($q, $s) => $q->where('guarantor_status', $s))
            ->when($request->boolean('registered'), fn ($q) => $q->whereNotNull('guarantor_registered_at'))
            ->withCount(['guarantees as active_guarantees' => fn ($q) => $q->where('status', 'accepted')->whereHas('loan', fn ($l) => $l->whereIn('status', ['approved', 'disbursed']))])
            ->orderByDesc('active_guarantees')->orderBy('member_no')->paginate(30, ['*'], 'rp')->withQueryString();

        $stats = [
            'active' => LoanGuarantor::where('status', 'accepted')->whereHas('loan', fn ($l) => $l->where('status', 'disbursed'))->count(),
            'exposure' => LoanGuarantor::with('loan')->where('status', 'accepted')->whereHas('loan', fn ($l) => $l->where('status', 'disbursed'))->get()->sum(fn ($g) => $g->exposure()),
            'pending' => $pendingConsents->count(), 'releases' => $releaseRequests->count(),
            'blocked' => Member::whereIn('guarantor_status', ['blocked', 'suspended'])->count(),
            'registered' => Member::whereNotNull('guarantor_registered_at')->count(),
        ];
        return view('guarantors.index', compact('tab', 'guarantees', 'releaseRequests', 'pendingConsents', 'register', 'stats'));
    }

    /** One member's guarantor profile. */
    public function show(Member $member, Request $request)
    {
        $p = $this->svc->profile($member);
        $check = $request->filled('amount') ? $this->svc->eligibility($member, $request->borrower ? Member::find($request->borrower) : null, (float) $request->amount) : null;
        return view('guarantors.show', compact('member', 'p', 'check'));
    }

    /** Printable guarantor liability statement for one member. */
    public function statement(Member $member)
    {
        return view('guarantors.statement', ['member' => $member, 'p' => $this->svc->profile($member)]);
    }

    /** JSON eligibility check used by the loan forms. */
    public function check(Request $request)
    {
        $d = $request->validate(['member_id' => 'required|exists:members,id', 'amount' => 'required|numeric|min:0', 'borrower_id' => 'nullable|exists:members,id', 'loan_id' => 'nullable|exists:loans,id']);
        $e = $this->svc->eligibility(Member::findOrFail($d['member_id']), isset($d['borrower_id']) ? Member::find($d['borrower_id']) : null,
            (float) $d['amount'], isset($d['loan_id']) ? Loan::find($d['loan_id']) : null);
        return response()->json($e);
    }

    public function register(Member $member, Request $request)
    {
        $this->svc->register($member, $request->user());
        return back()->with('success', "{$member->full_name} is registered as a guarantor.");
    }

    public function updateStatus(Request $request, Member $member)
    {
        $d = $request->validate(['guarantor_status' => 'required|in:eligible,suspended,blocked', 'guarantor_note' => 'nullable|required_unless:guarantor_status,eligible|string|max:255',
            'max_guarantee_amount' => 'nullable|numeric|min:0']);
        $this->svc->setStatus($member, $d['guarantor_status'], $d['guarantor_note'] ?? null,
            isset($d['max_guarantee_amount']) && $d['max_guarantee_amount'] !== '' ? (float) $d['max_guarantee_amount'] : null, $request->user());
        return back()->with('success', 'Guarantor settings updated.');
    }

    /** Staff add a guarantor to a loan (from a paper form, or to top up cover). */
    public function add(Request $request, Loan $loan)
    {
        $d = $request->validate(['member_id' => 'required|exists:members,id', 'amount' => 'required|numeric|min:1']);
        try { $this->svc->request($loan, Member::findOrFail($d['member_id']), (float) $d['amount'], 'staff'); }
        catch (RuntimeException $e) { return back()->withInput()->with('error', $e->getMessage()); }
        return back()->with('success', 'Guarantor added and asked to consent by SMS. Record a paper consent below if they signed a form.');
    }

    public function decideRelease(Request $request, LoanGuarantor $guarantor)
    {
        $d = $request->validate(['decision' => 'required|in:approve,reject', 'note' => 'nullable|string|max:255']);
        try { $this->svc->decideRelease($guarantor, $d['decision'] === 'approve', $request->user(), $d['note'] ?? null); }
        catch (RuntimeException $e) { return back()->with('error', $e->getMessage()); }
        return back()->with('success', $d['decision'] === 'approve' ? 'Guarantor released and notified.' : 'Request declined and the guarantor informed.');
    }

    public function expireNow()
    {
        $r = $this->svc->expireRequests();
        return back()->with('success', "{$r['expired']} request(s) lapsed, {$r['nudged']} guarantor(s) reminded.");
    }
}
