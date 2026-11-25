<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\FraudAlert;
use App\Services\FraudMonitor;
use Illuminate\Http\Request;

class FraudController extends Controller
{
    public function index(Request $request)
    {
        $alerts = FraudAlert::with('member', 'transaction', 'staff', 'reviewer')
            ->when($request->status, fn ($q, $s) => $q->where('status', $s), fn ($q) => $q->whereIn('status', ['open', 'investigating']))
            ->when($request->severity, fn ($q, $s) => $q->where('severity', $s))
            ->when($request->rule, fn ($q, $r) => $q->where('rule', $r))
            ->orderByRaw("FIELD(severity, 'high', 'medium', 'low')")->latest()->paginate(30)->withQueryString();
        $counts = FraudAlert::whereIn('status', ['open', 'investigating'])->selectRaw('severity, COUNT(*) n')->groupBy('severity')->pluck('n', 'severity');
        $byRule = FraudAlert::where('created_at', '>=', now()->subDays(30))->selectRaw('rule, COUNT(*) n')->groupBy('rule')->pluck('n', 'rule');
        return view('fraud.index', ['alerts' => $alerts, 'counts' => $counts, 'byRule' => $byRule, 'rules' => FraudMonitor::RULES]);
    }

    public function update(Request $request, FraudAlert $alert)
    {
        $d = $request->validate(['status' => 'required|in:open,investigating,cleared,reported', 'review_notes' => 'required_if:status,cleared,reported|nullable|string|max:3000',
            'str_reference' => 'required_if:status,reported|nullable|string|max:60']);
        $alert->update($d + ['reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
        AuditLog::record('fraud_alert_review', $alert, ['status' => $d['status']]);
        return back()->with('success', 'Alert updated.');
    }

    public function scan(FraudMonitor $monitor)
    {
        return back()->with('success', $monitor->scan() . ' new alert(s) from the pattern scan.');
    }
}
