<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Member;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Member withdrawal & clearance:
 *   1. guarantees must be zero (substitute guarantors first)
 *   2. running loans are offset from deposits, then savings
 *   3. remaining deposits and savings are refunded through the chosen channel
 *   4. share capital is NOT refunded (SASRA) - it must be transferred to another member
 */
class MemberExitService
{
    public function __construct(private AccountService $accounts, private LoanService $loans, private SmsService $sms) {}

    public function preview(Member $member): array
    {
        $member->load('accounts.type');
        $dep = $member->balance('DEP'); $sav = $member->balance('SAV'); $loans = $member->outstandingLoans();
        $guarantees = $member->committedGuarantees();
        $issues = [];
        if ($guarantees > 0) $issues[] = 'Member still guarantees KES ' . number_format($guarantees, 2) . ' on other loans.';
        if ($loans > $dep + $sav) $issues[] = 'Deposits and savings do not cover the loan balance.';
        if ($member->fixedDeposits()->where('status', 'active')->exists()) $issues[] = 'Close active fixed deposits first.';

        return ['deposits' => $dep, 'savings' => $sav, 'shares' => $member->balance('SHR'), 'loans' => $loans,
                'guarantees' => $guarantees, 'refund' => max(0, round($dep + $sav - $loans, 2)), 'issues' => $issues];
    }

    public function process(Member $member, string $channel, ?string $reason, ?string $ref = null): array
    {
        $p = $this->preview($member);
        if ($p['issues']) throw new RuntimeException(implode(' ', $p['issues']));

        DB::transaction(function () use ($member, $channel, $reason, $ref) {
            // Offset loans from deposits first, then savings.
            foreach ($member->loans()->where('status', 'disbursed')->get() as $loan) {
                foreach (['DEP', 'SAV'] as $code) {
                    $loan->refresh();
                    if ($loan->status !== 'disbursed') break;
                    $acc = $this->accounts->accountFor($member, $code);
                    $take = min((float) $acc->fresh()->balance, $loan->outstanding);
                    if ($take <= 0) continue;
                    $this->accounts->post($acc, 'transfer_out', 'debit', $take, ['channel' => 'internal', 'loan_id' => $loan->id, 'description' => "Exit offset to {$loan->loan_no}"]);
                    $this->loans->repay($loan, $take, 'internal', 'EXIT');
                }
            }
            foreach (['DEP', 'SAV'] as $code) {
                $bal = (float) $this->accounts->accountFor($member, $code)->fresh()->balance;
                if ($bal > 0) $this->accounts->withdraw($member, $code, $bal, ['force' => true, 'channel' => $channel, 'external_ref' => $ref, 'description' => "Exit refund ($code)"]);
            }
            $member->update(['status' => 'exited', 'exited_on' => today(), 'exit_reason' => $reason]);
            $member->user?->update(['is_active' => false]);
            AuditLog::record('member_exited', $member, ['reason' => $reason]);
        });

        $this->sms->send($member->phone, "Dear {$member->first_name}, your membership exit has been processed. Refund: KES "
            . number_format($p['refund'], 2) . '. Share capital of KES ' . number_format($p['shares'], 2) . ' is held for transfer.', $member->id);
        return $p;
    }

    /** Transfer share capital from an exiting member to another member (sale of shares). */
    public function transferShares(Member $from, Member $to, float $amount): void
    {
        if ($amount > $from->balance('SHR')) throw new RuntimeException('Amount exceeds share balance.');
        $this->accounts->transfer($this->accounts->accountFor($from, 'SHR'), $this->accounts->accountFor($to, 'SHR'), $amount,
            "Share transfer {$from->member_no} -> {$to->member_no}");
        AuditLog::record('share_transfer', $from, ['to' => $to->member_no, 'amount' => $amount]);
    }
}
