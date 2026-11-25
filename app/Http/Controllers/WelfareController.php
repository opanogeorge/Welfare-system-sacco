<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\WelfareBenefit;
use App\Models\WelfareContribution;
use App\Models\WelfareEvent;
use App\Services\WelfareService;
use Illuminate\Http\Request;
use RuntimeException;

class WelfareController extends Controller
{
    public function __construct(private WelfareService $welfare) {}

    public function index(Request $request)
    {
        $events = WelfareEvent::with('member')->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->type, fn ($q, $t) => $q->where('type', $t))->latest()->paginate(25)->withQueryString();
        $stats = [
            'fund' => $this->welfare->fundBalance(),
            'open' => WelfareEvent::where('status', 'open')->count(),
            'requested' => WelfareEvent::where('status', 'requested')->count(),
            'due' => (float) WelfareContribution::where('status', 'due')->sum('amount'),
            'year_paid' => (float) WelfareEvent::whereYear('paid_on', now()->year)->sum('paid_out'),
            'monthly' => (float) WelfareContribution::where('kind', 'monthly')->where('status', 'paid')->where('period', now()->format('Y-m'))->sum('amount'),
        ];
        return view('welfare.index', compact('events', 'stats'));
    }

    public function create(Request $request)
    {
        return view('welfare.form', ['event' => new WelfareEvent(['mode' => 'levy', 'levy_source' => 'SAV', 'type' => 'bereavement', 'event_date' => today()]),
            'member' => $request->member ? Member::find($request->member) : null, 'benefits' => WelfareBenefit::where('is_active', true)->get()]);
    }

    public function store(Request $request)
    {
        $d = $this->validated($request);
        if ($request->hasFile('document')) $d['document'] = $request->file('document')->store('welfare', 'local');
        $e = $this->welfare->create($d, $request->user());
        if ($request->boolean('open_now') && $request->user()->hasPermission('welfare.approve')) {
            try { $r = $this->welfare->open($e, $request->user(), $request->boolean('announce', true)); }
            catch (RuntimeException $ex) { return redirect()->route('welfare.show', $e)->with('error', $ex->getMessage()); }
            return redirect()->route('welfare.show', $e)->with('success', $this->openedMsg($r));
        }
        return redirect()->route('welfare.show', $e)->with('success', 'Event saved as draft. A manager can open it for contributions.');
    }

    public function show(WelfareEvent $welfare)
    {
        $welfare->load('member', 'creator', 'approver');
        $contributions = $welfare->contributions()->with('member')->orderByRaw("FIELD(status, 'due', 'paid', 'waived')")->latest('paid_at')->paginate(50);
        $summary = $welfare->contributions()->selectRaw('status, kind, COUNT(*) n, SUM(amount) total')->groupBy('status', 'kind')->get();
        return view('welfare.show', ['e' => $welfare, 'contributions' => $contributions, 'summary' => $summary, 'fund' => $this->welfare->fundBalance(),
            'announcement' => $this->welfare->announcement($welfare)]);
    }

    public function edit(WelfareEvent $welfare)
    {
        abort_unless(in_array($welfare->status, ['draft', 'requested']), 403, 'Only draft or requested events can be edited.');
        return view('welfare.form', ['event' => $welfare, 'member' => $welfare->member, 'benefits' => WelfareBenefit::where('is_active', true)->get()]);
    }

    public function update(Request $request, WelfareEvent $welfare)
    {
        abort_unless(in_array($welfare->status, ['draft', 'requested']), 403);
        $d = $this->validated($request);
        if ($request->hasFile('document')) $d['document'] = $request->file('document')->store('welfare', 'local');
        $welfare->update($d);
        return redirect()->route('welfare.show', $welfare)->with('success', 'Event updated.');
    }

    public function open(Request $request, WelfareEvent $welfare)
    {
        try { $r = $this->welfare->open($welfare, $request->user(), $request->boolean('announce', true)); }
        catch (RuntimeException $e) { return back()->with('error', $e->getMessage()); }
        return back()->with('success', $this->openedMsg($r));
    }

    public function reject(Request $request, WelfareEvent $welfare)
    {
        $request->validate(['reason' => 'required|string|max:255']);
        $welfare->update(['status' => 'rejected', 'details' => trim($welfare->details . "\n\nRejected: " . $request->reason)]);
        app(\App\Services\NotificationService::class)->toMember($welfare->member, 'Dear {first_name}, your welfare request "' . $welfare->title . '" was not approved: ' . $request->reason . ' - ' . sacco_name());
        return back()->with('success', 'Request rejected and the member informed.');
    }

    public function contribute(Request $request, WelfareEvent $welfare)
    {
        $d = $request->validate(['member_id' => 'required|exists:members,id', 'amount' => 'required|numeric|min:1', 'channel' => 'required|in:cash,mpesa,bank,savings', 'reference' => 'nullable|string|max:60']);
        $member = Member::findOrFail($d['member_id']);
        try {
            $d['channel'] === 'savings'
                ? $this->welfare->giveFromSavings($member, $welfare, (float) $d['amount'])
                : $this->welfare->receive($member, (float) $d['amount'], $welfare->id, $d['channel'], $d['reference'] ?? null, $request->user()->id);
        } catch (RuntimeException $e) { return back()->withInput()->with('error', $e->getMessage()); }
        return back()->with('success', 'Contribution of KES ' . number_format($d['amount'], 2) . " from {$member->full_name} recorded.");
    }

    public function retry(Request $request, WelfareEvent $welfare)
    {
        $r = $this->welfare->retryDeductions($welfare, $request->user());
        return back()->with('success', "Deducted from {$r['deducted']} member(s). Still unpaid: {$r['due']}.");
    }

    public function remind(WelfareEvent $welfare)
    {
        return back()->with('success', 'Reminder sent to ' . $this->welfare->remindDue($welfare) . ' member(s) with unpaid contributions.');
    }

    public function announce(WelfareEvent $welfare)
    {
        return back()->with('success', 'Announcement sent to ' . $this->welfare->announce($welfare) . ' member(s).');
    }

    public function waive(Request $request, WelfareContribution $contribution)
    {
        try { $this->welfare->waive($contribution, $request->user()); } catch (RuntimeException $e) { return back()->with('error', $e->getMessage()); }
        return back()->with('success', 'Contribution waived.');
    }

    public function close(WelfareEvent $welfare)
    {
        abort_unless($welfare->status === 'open', 403);
        $welfare->update(['status' => 'closed']);
        return back()->with('success', 'Event closed for contributions. Unpaid levies stay on members\' records until paid or waived.');
    }

    public function payout(Request $request, WelfareEvent $welfare)
    {
        $d = $request->validate(['amount' => 'required|numeric|min:1', 'channel' => 'required|in:savings,cash,bank,mpesa,cheque', 'reference' => 'nullable|string|max:60']);
        try { $this->welfare->payout($welfare, (float) $d['amount'], $d['channel'], $d['reference'] ?? null, $request->user()); }
        catch (RuntimeException $e) { return back()->with('error', $e->getMessage()); }
        return back()->with('success', 'KES ' . number_format($d['amount'], 2) . " paid to {$welfare->member->full_name}.");
    }

    public function document(WelfareEvent $welfare)
    {
        abort_unless($welfare->document && \Storage::disk('local')->exists($welfare->document), 404);
        return \Storage::disk('local')->download($welfare->document);
    }

    /* ---------- Benefit schedule & monthly contributions ---------- */

    public function benefits()
    {
        $monthly = WelfareContribution::with('member')->where('kind', 'monthly')->latest()->take(100)->get();
        $byPeriod = WelfareContribution::where('kind', 'monthly')->selectRaw("period, status, COUNT(*) n, SUM(amount) total")->groupBy('period', 'status')->orderByDesc('period')->get()->groupBy('period')->take(12);
        return view('welfare.benefits', ['benefits' => WelfareBenefit::orderBy('type')->orderBy('relationship')->get(), 'monthly' => $monthly, 'byPeriod' => $byPeriod]);
    }

    public function saveBenefit(Request $request)
    {
        $d = $request->validate(['id' => 'nullable|exists:welfare_benefits,id', 'type' => 'required|in:' . implode(',', array_keys(WelfareEvent::TYPES)),
            'relationship' => 'nullable|in:' . implode(',', array_keys(WelfareEvent::RELATIONSHIPS)), 'amount' => 'required|numeric|min:0']);
        WelfareBenefit::updateOrCreate(['id' => $d['id'] ?? null], $d + ['is_active' => $request->boolean('is_active', true)]);
        return back()->with('success', 'Benefit saved.');
    }

    public function deleteBenefit(WelfareBenefit $benefit)
    {
        $benefit->delete();
        return back()->with('success', 'Benefit removed.');
    }

    public function chargeMonthly(Request $request)
    {
        if ((float) setting('welfare_monthly_amount', 0) <= 0) return back()->with('error', 'Set "Monthly welfare contribution" in Settings first.');
        $request->validate(['period' => 'nullable|date_format:Y-m']);
        $r = $this->welfare->chargeMonthly($request->input('period') ?: null);
        return back()->with('success', "Monthly welfare: {$r['charged']} charged, {$r['deducted']} deducted, {$r['due']} left unpaid.");
    }

    public function benefitFor(Request $request)
    {
        return ['amount' => WelfareBenefit::amountFor((string) $request->type, $request->relationship ?: null)];
    }

    private function openedMsg(array $r): string
    {
        return 'Event opened and members notified.' . ($r['charged'] || $r['due'] ? " Levy: {$r['deducted']} deducted, {$r['due']} still to pay." : '');
    }

    private function validated(Request $request): array
    {
        $d = $request->validate([
            'member_id' => 'required|exists:members,id', 'type' => 'required|in:' . implode(',', array_keys(WelfareEvent::TYPES)),
            'relationship' => 'nullable|in:' . implode(',', array_keys(WelfareEvent::RELATIONSHIPS)), 'title' => 'required|string|max:150',
            'details' => 'nullable|string|max:3000', 'event_date' => 'nullable|date', 'mode' => 'required|in:voluntary,levy,both',
            'levy_amount' => 'nullable|required_unless:mode,voluntary|numeric|min:0', 'levy_source' => 'required|in:SAV,DEP,none',
            'suggested_amount' => 'nullable|numeric|min:0', 'target_amount' => 'nullable|numeric|min:0', 'fund_contribution' => 'nullable|numeric|min:0',
            'closes_on' => 'nullable|date', 'document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);
        unset($d['document']);
        foreach (['levy_amount', 'suggested_amount', 'target_amount', 'fund_contribution'] as $k) $d[$k] = (float) ($d[$k] ?? 0);
        return $d;
    }
}
