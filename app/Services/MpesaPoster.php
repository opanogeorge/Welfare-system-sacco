<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\Member;
use App\Models\MpesaTransaction;
use App\Models\User;

/**
 * Routes a C2B payment using the account number the payer typed:
 *   LN2026xxxxx          -> loan repayment
 *   MBR00001             -> deposits (BOSA) by default
 *   MBR00001-SHR / -SAV  -> specific account
 *   MBR00001-W12 / W12   -> contribution to welfare event 12
 *   MBR00001-WLF         -> monthly welfare contribution / welfare dues
 *   phone number match   -> deposits of the member with that phone
 */
class MpesaPoster
{
    public function __construct(private AccountService $accounts, private LoanService $loans) {}

    public function post(MpesaTransaction $m): void
    {
        $ref = strtoupper(trim((string) $m->bill_ref));
        $system = User::where('role', 'admin')->value('id');
        $date = ($m->trans_time ?? now())->copy()->startOfDay();

        try {
            if (str_starts_with($ref, 'LN') && ($loan = Loan::where('loan_no', $ref)->where('status', 'disbursed')->first())) {
                $r = $this->loans->repay($loan, (float) $m->amount, 'mpesa', $m->trans_id, $date, $system);
                $m->update(['status' => 'posted', 'transaction_id' => null]);
                return;
            }

            [$memberNo, $code] = array_pad(explode('-', $ref, 2), 2, 'DEP');
            $member = Member::where('member_no', $memberNo)->first()
                ?? Member::where('phone', 'like', '%' . substr(preg_replace('/\D/', '', (string) $m->msisdn), -9))->first();

            if (! $member && preg_match('/^W\d+$/', $memberNo)) {          // "W12" typed without a member number
                [$code, $member] = [$memberNo, Member::where('phone', 'like', '%' . substr(preg_replace('/\D/', '', (string) $m->msisdn), -9))->first()];
            }
            if (! $member) {
                $m->update(['status' => 'unmatched']);
                return;
            }

            if (preg_match('/^W(\d+)$/', $code, $w) || $code === 'WLF') {
                $tx = app(WelfareService::class)->receive($member, (float) $m->amount, isset($w[1]) ? (int) $w[1] : null, 'mpesa', $m->trans_id, $system);
                $m->update(['status' => 'posted', 'transaction_id' => $tx->id]);
                return;
            }

            $tx = $this->accounts->deposit($member, $code ?: 'DEP', (float) $m->amount, [
                'channel' => 'mpesa', 'external_ref' => $m->trans_id, 'date' => $date, 'posted_by' => $system,
                'description' => "M-Pesa from {$m->payer_name} ({$m->msisdn})",
            ]);
            $m->update(['status' => 'posted', 'transaction_id' => $tx->id]);
        } catch (\Throwable $e) {
            $m->update(['status' => 'unmatched']);
            report($e);
        }
    }
}
