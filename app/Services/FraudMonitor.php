<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\FraudAlert;
use App\Models\LoanGuarantor;
use App\Models\Member;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Suspicious-transaction and fraud monitoring.
 *  - Real-time rules run on every member transaction (never block the posting).
 *  - Nightly rules (sacco:fraud-scan) look for patterns across members and staff.
 * Alerts go to a review queue; confirmed cases are reported to the Financial Reporting Centre (goAML).
 */
class FraudMonitor
{
    public const RULES = [
        'large_cash'        => ['Large cash transaction', 'high'],
        'structuring'       => ['Possible structuring (split cash deposits)', 'high'],
        'velocity'          => ['Unusual number of transactions', 'medium'],
        'rapid_in_out'      => ['Funds in and out quickly', 'high'],
        'dormant_activity'  => ['Large activity on dormant account', 'medium'],
        'amount_anomaly'    => ['Amount far above member\'s normal pattern', 'medium'],
        'after_hours'       => ['Cash posted outside working hours', 'medium'],
        'backdated'         => ['Back-dated posting', 'low'],
        'self_posting'      => ['Staff posted to own member account', 'high'],
        'shared_contact'    => ['Same phone/email on several members', 'medium'],
        'reversal_pattern'  => ['Frequent reversals by one user', 'high'],
        'guarantee_concentration' => ['Member guaranteeing many loans', 'low'],
    ];

    private function cfg(string $k, $default)
    {
        return Setting::get("fraud_$k", $default);
    }

    /* ---------------- Real-time ---------------- */

    public function inspect(Transaction $tx): void
    {
        if ($tx->channel === 'internal' || ! $tx->member_id || in_array($tx->type, ['reversal', 'penalty', 'interest', 'dividend'], true)) return;
        try {
            $amount = (float) $tx->amount;
            $threshold = (float) Setting::get('aml_cash_threshold', 1000000);
            $member = $tx->member;

            if ($tx->channel === 'cash' && $threshold > 0 && $amount >= $threshold) {
                $this->raise('large_cash', $tx, "Cash {$tx->type} of KES " . number_format($amount, 2) . ' (reporting threshold KES ' . number_format($threshold) . ')', 90);
            }

            if ($tx->channel === 'cash' && $tx->type === 'deposit' && $amount >= 0.5 * $threshold && $amount < $threshold) {
                $recent = Transaction::where('member_id', $tx->member_id)->where('channel', 'cash')->where('type', 'deposit')
                    ->where('created_at', '>=', now()->subDays(7))->whereBetween('amount', [0.5 * $threshold, $threshold - 0.01]);
                if ((clone $recent)->count() >= 2 && (clone $recent)->sum('amount') >= $threshold) {
                    $this->raise('structuring', $tx, (clone $recent)->count() . ' cash deposits just under the threshold in 7 days, total KES ' . number_format((clone $recent)->sum('amount'), 2), 85, 'member', 7);
                }
            }

            $count = Transaction::where('member_id', $tx->member_id)->where('channel', '!=', 'internal')->where('created_at', '>=', now()->subDay())->count();
            if ($count > (int) $this->cfg('velocity_24h', 10)) {
                $this->raise('velocity', $tx, "$count transactions in 24 hours", 60, 'member', 1);
            }

            if ($tx->type === 'withdrawal' && $amount >= (float) $this->cfg('rapid_min', 50000)) {
                $in = (float) Transaction::where('member_id', $tx->member_id)->where('direction', 'credit')->whereIn('type', ['deposit', 'loan_disbursement', 'transfer_in'])
                    ->where('created_at', '>=', now()->subHours(48))->sum('amount');
                if ($in > 0 && $amount >= 0.8 * $in) {
                    $this->raise('rapid_in_out', $tx, 'Withdrew KES ' . number_format($amount, 2) . ' of KES ' . number_format($in, 2) . ' received in the last 48 hours', 80);
                }
            }

            if ($amount >= (float) $this->cfg('dormant_min', 50000)) {
                $last = Transaction::where('member_id', $tx->member_id)->where('id', '<', $tx->id)->where('channel', '!=', 'internal')->max('created_at');
                if ($member?->status === 'dormant' || ($last && now()->diffInDays($last, true) >= 180)) {
                    $this->raise('dormant_activity', $tx, 'KES ' . number_format($amount, 2) . ' on an account inactive since ' . ($last ? \Carbon\Carbon::parse($last)->format('d M Y') : 'joining'), 65);
                }
            }

            $hist = Transaction::where('member_id', $tx->member_id)->where('type', $tx->type)->where('id', '<', $tx->id)->latest('id')->take(50)->pluck('amount')->map(fn ($v) => (float) $v);
            if ($hist->count() >= 8 && $amount >= (float) $this->cfg('anomaly_min', 20000)) {
                $mean = $hist->avg(); $sd = sqrt($hist->map(fn ($v) => ($v - $mean) ** 2)->avg()) ?: 1;
                $zScore = ($amount - $mean) / $sd;
                if ($zScore >= (float) $this->cfg('anomaly_z', 4)) {
                    $this->raise('amount_anomaly', $tx, sprintf('KES %s vs usual KES %s (%.1f standard deviations above normal)', number_format($amount), number_format($mean), $zScore), 60);
                }
            }

            $poster = $tx->posted_by ? User::find($tx->posted_by) : null;
            if ($poster && $poster->isStaff()) {
                $h = (int) now()->format('G');
                if ($tx->channel === 'cash' && ($h < (int) $this->cfg('work_start', 7) || $h >= (int) $this->cfg('work_end', 19) || now()->isSunday())) {
                    $this->raise('after_hours', $tx, "Cash {$tx->type} posted by {$poster->name} at " . now()->format('D H:i'), 55, null, null, $poster->id);
                }
                if ($tx->txn_date && $tx->txn_date->lt(today()->subDays((int) $this->cfg('backdate_days', 3)))) {
                    $this->raise('backdated', $tx, "Posted on " . today()->format('d/m') . " with date " . $tx->txn_date->format('d/m/Y') . " by {$poster->name}", 35, null, null, $poster->id);
                }
                if ($poster->member && $poster->member->id === $tx->member_id) {
                    $this->raise('self_posting', $tx, "{$poster->name} posted a {$tx->type} to their own account", 85, null, null, $poster->id);
                }
            }
        } catch (\Throwable $e) {
            report($e); // monitoring must never stop a posting
        }
    }

    /* ---------------- Nightly ---------------- */

    public function scan(): int
    {
        $before = FraudAlert::count();

        foreach (['phone', 'email'] as $field) {
            Member::whereNotNull($field)->where($field, '!=', '')->whereIn('status', ['active', 'dormant', 'pending'])
                ->select($field, DB::raw('COUNT(*) n'))->groupBy($field)->having('n', '>', 1)->get()
                ->each(function ($row) use ($field) {
                    $members = Member::where($field, $row->$field)->get();
                    $this->raiseFor('shared_contact', $members->first(), null,
                        "$field {$row->$field} is used by {$row->n} members: " . $members->pluck('member_no')->implode(', '), 55, 30, ['members' => $members->pluck('id')]);
                });
        }

        \App\Models\AuditLog::where('action', 'reversal')->where('created_at', '>=', now()->subDays(7))
            ->select('user_id', DB::raw('COUNT(*) n'))->groupBy('user_id')->having('n', '>=', (int) $this->cfg('reversals_week', 5))->get()
            ->each(function ($row) {
                $u = User::find($row->user_id);
                if ($u) $this->raiseFor('reversal_pattern', null, $u->id, "{$u->name} made {$row->n} reversals in 7 days", 75, 7);
            });

        LoanGuarantor::where('status', 'accepted')->whereHas('loan', fn ($q) => $q->where('status', 'disbursed'))
            ->select('member_id', DB::raw('COUNT(*) n'))->groupBy('member_id')->having('n', '>', (int) $this->cfg('max_guarantees', 5))->get()
            ->each(function ($row) {
                $m = Member::find($row->member_id);
                if ($m) $this->raiseFor('guarantee_concentration', $m, null, "Guarantees {$row->n} running loans", 40, 30);
            });

        return FraudAlert::count() - $before;
    }

    /* ---------------- Helpers ---------------- */

    private function raise(string $rule, Transaction $tx, string $summary, int $score, ?string $dedupe = null, ?int $days = null, ?int $staffId = null): void
    {
        if ($dedupe === 'member' && FraudAlert::where('rule', $rule)->where('member_id', $tx->member_id)->where('created_at', '>=', now()->subDays($days ?? 1))->exists()) return;
        $this->store($rule, $tx->member_id, $tx->id, $staffId, $summary, $score, ['amount' => (float) $tx->amount, 'type' => $tx->type, 'channel' => $tx->channel, 'reference' => $tx->reference]);
    }

    private function raiseFor(string $rule, ?Member $m, ?int $staffId, string $summary, int $score, int $dedupeDays, array $details = []): void
    {
        $q = FraudAlert::where('rule', $rule)->where('created_at', '>=', now()->subDays($dedupeDays));
        $m ? $q->where('member_id', $m->id) : $q->where('staff_id', $staffId);
        if ($q->exists()) return;
        $this->store($rule, $m?->id, null, $staffId, $summary, $score, $details);
    }

    private function store(string $rule, ?int $memberId, ?int $txId, ?int $staffId, string $summary, int $score, array $details): void
    {
        $severity = self::RULES[$rule][1] ?? 'medium';
        FraudAlert::create(['rule' => $rule, 'severity' => $severity, 'score' => $score, 'member_id' => $memberId, 'transaction_id' => $txId,
            'staff_id' => $staffId, 'summary' => mb_substr($summary, 0, 255), 'details' => $details]);
        if ($severity === 'high') AuditLog::record('fraud_alert', null, ['rule' => $rule, 'member_id' => $memberId, 'summary' => $summary]);
    }
}
