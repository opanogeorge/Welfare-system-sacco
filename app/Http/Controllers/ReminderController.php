<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\ReminderLog;
use App\Models\ReminderRule;
use App\Services\ReminderService;
use Illuminate\Http\Request;

class ReminderController extends Controller
{
    public function index(Request $request, ReminderService $svc)
    {
        ReminderService::seedDefaults();
        $rules = ReminderRule::withCount(['logs as sent_30' => fn ($q) => $q->where('created_at', '>=', now()->subDays(30))])->orderBy('event')->orderBy('days')->get();
        $logs = ReminderLog::with('member', 'loan', 'rule', 'sender')
            ->when($request->rule, fn ($q, $r) => $q->where('reminder_rule_id', $r))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->q, fn ($q, $s) => $q->whereHas('member', fn ($m) => $m->where('member_no', 'like', "%$s%")->orWhere('first_name', 'like', "%$s%")->orWhere('last_name', 'like', "%$s%")))
            ->latest()->paginate(40)->withQueryString();
        $preview = $request->boolean('preview') ? $svc->run(true)['items'] : null;
        $stats = ['today' => ReminderLog::whereDate('created_at', today())->count(), 'week' => ReminderLog::where('created_at', '>=', now()->subDays(7))->count(),
                  'failed' => ReminderLog::where('status', 'failed')->where('created_at', '>=', now()->subDays(7))->count()];
        return view('reminders.index', compact('rules', 'logs', 'preview', 'stats'));
    }

    public function store(Request $request)
    {
        ReminderRule::create($this->validated($request));
        return back()->with('success', 'Reminder rule added.');
    }

    public function update(Request $request, ReminderRule $rule)
    {
        $rule->update($this->validated($request));
        return back()->with('success', "“{$rule->name}” updated.");
    }

    public function toggle(ReminderRule $rule)
    {
        $rule->update(['is_active' => ! $rule->is_active]);
        return back()->with('success', $rule->is_active ? 'Rule switched on.' : 'Rule switched off.');
    }

    public function destroy(ReminderRule $rule)
    {
        $rule->delete();
        return back()->with('success', 'Rule deleted.');
    }

    public function runNow(ReminderService $svc)
    {
        $r = $svc->run();
        return back()->with('success', "Sent {$r['sent']} reminder(s)" . ($r['failed'] ? ", {$r['failed']} failed (check SMS settings)" : '') . '. Each reminder goes only once.');
    }

    public function sendLoan(Request $request, Loan $loan, ReminderService $svc)
    {
        $request->validate(['message' => 'nullable|string|max:480']);
        $ok = $svc->sendLoanReminderNow($loan, $request->user(), $request->input('message'));
        return back()->with($ok ? 'success' : 'error', $ok ? 'Reminder SMS sent to ' . $loan->member->phone . '.' : 'The SMS could not be sent. Check Admin → Integrations.');
    }

    private function validated(Request $request): array
    {
        $d = $request->validate([
            'name' => 'required|string|max:120', 'event' => 'required|in:' . implode(',', array_keys(ReminderService::EVENTS)),
            'days' => 'required|integer|min:0|max:365', 'channel' => 'required|in:sms,email,both', 'message' => 'required|string|max:480',
            'guarantor_message' => 'nullable|string|max:480',
        ]);
        return $d + ['notify_guarantors' => $request->boolean('notify_guarantors'), 'is_active' => $request->boolean('is_active', true)];
    }
}
