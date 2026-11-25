<?php

namespace App\Services;

use App\Models\AccountType;
use App\Models\AuditLog;
use App\Models\Member;
use App\Models\MemberAccount;
use App\Models\Setting;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Every balance change in the SACCO goes through post(): the account row is locked,
 * the balance updated and a ledger line written in one DB transaction.
 */
class AccountService
{
    public function openDefaultAccounts(Member $member): void
    {
        foreach (AccountType::where('is_active', true)->get() as $type) {
            MemberAccount::firstOrCreate(
                ['member_id' => $member->id, 'account_type_id' => $type->id],
                ['account_no' => NumberGenerator::accountNo($member, $type->code)]
            );
        }
    }

    public function accountFor(Member $member, string $code): MemberAccount
    {
        $type = AccountType::where('code', $code)->firstOrFail();
        return MemberAccount::firstOrCreate(
            ['member_id' => $member->id, 'account_type_id' => $type->id],
            ['account_no' => NumberGenerator::accountNo($member, $code)]
        );
    }

    public function post(
        MemberAccount $account,
        string $type,
        string $direction,
        float $amount,
        array $meta = []
    ): Transaction {
        if ($amount <= 0) {
            throw new RuntimeException('Amount must be greater than zero.');
        }

        return DB::transaction(function () use ($account, $type, $direction, $amount, $meta) {
            /** @var MemberAccount $acc */
            $acc = MemberAccount::whereKey($account->id)->lockForUpdate()->first();

            $new = $direction === 'credit'
                ? (float) $acc->balance + $amount
                : (float) $acc->balance - $amount;

            if ($direction === 'debit' && $new < 0 && empty($meta['allow_negative'])) {
                throw new RuntimeException("Insufficient balance on {$acc->account_no}.");
            }

            $acc->update(['balance' => round($new, 2)]);

            // AML: flag large cash movements for the compliance officer (POCAMLA reporting threshold).
            $threshold = (float) Setting::get('aml_cash_threshold', 1000000);
            if (($meta['channel'] ?? 'cash') === 'cash' && $threshold > 0 && $amount >= $threshold) {
                AuditLog::record('aml_flag', $acc, ['amount' => $amount, 'type' => $type, 'member_id' => $acc->member_id]);
            }

            return Transaction::create([
                'reference'         => $meta['reference'] ?? NumberGenerator::reference(),
                'member_id'         => $acc->member_id,
                'member_account_id' => $acc->id,
                'loan_id'           => $meta['loan_id'] ?? null,
                'type'              => $type,
                'direction'         => $direction,
                'amount'            => round($amount, 2),
                'balance_after'     => round($new, 2),
                'channel'           => $meta['channel'] ?? 'cash',
                'external_ref'      => $meta['external_ref'] ?? null,
                'description'       => $meta['description'] ?? null,
                'txn_date'          => $meta['date'] ?? today(),
                'posted_by'         => $meta['posted_by'] ?? auth()->id(),
            ]);
        });
    }

    public function deposit(Member $member, string $code, float $amount, array $meta = []): Transaction
    {
        $tx = $this->post($this->accountFor($member, $code), 'deposit', 'credit', $amount,
            $meta + ['description' => 'Deposit to ' . strtoupper($code)]);
        AuditLog::record('deposit', $tx, ['amount' => $amount, 'account' => $code]);
        return $tx;
    }

    /** $meta['force'] = true allows withdrawing non-withdrawable accounts (member exit refunds only). */
    public function withdraw(Member $member, string $code, float $amount, array $meta = []): Transaction
    {
        $account = $this->accountFor($member, $code)->load('type');

        if (! empty($meta['force'])) {
            unset($meta['force']);
            return $this->post($account, 'withdrawal', 'debit', $amount, $meta + ['description' => 'Refund on exit']);
        }

        if (! $account->type->withdrawable) {
            throw new RuntimeException("{$account->type->name} is not withdrawable. Use member exit/refund instead.");
        }

        $fee = (float) $account->type->withdrawal_fee;
        if ($amount + $fee > $account->availableBalance()) {
            throw new RuntimeException('Insufficient available balance (KES ' . number_format($account->availableBalance(), 2) . ').');
        }

        return DB::transaction(function () use ($account, $amount, $fee, $meta) {
            $ref = $meta['reference'] ?? NumberGenerator::reference('WD');
            $tx = $this->post($account, 'withdrawal', 'debit', $amount,
                $meta + ['reference' => $ref, 'description' => 'Withdrawal']);
            if ($fee > 0) {
                $this->post($account, 'fee', 'debit', $fee,
                    ['reference' => $ref, 'description' => 'Withdrawal fee', 'channel' => 'internal']);
            }
            AuditLog::record('withdrawal', $tx, ['amount' => $amount]);
            return $tx;
        });
    }

    public function transfer(MemberAccount $from, MemberAccount $to, float $amount, string $description = 'Internal transfer'): void
    {
        DB::transaction(function () use ($from, $to, $amount, $description) {
            $ref = NumberGenerator::reference('TR');
            $this->post($from, 'transfer_out', 'debit', $amount, ['reference' => $ref, 'channel' => 'internal', 'description' => $description]);
            $this->post($to, 'transfer_in', 'credit', $amount, ['reference' => $ref, 'channel' => 'internal', 'description' => $description]);
        });
    }

    public function reverse(Transaction $tx, string $reason): Transaction
    {
        if ($tx->reversed) {
            throw new RuntimeException('Transaction already reversed.');
        }
        if (! $tx->member_account_id) {
            throw new RuntimeException('Loan transactions must be reversed from the loan screen.');
        }

        return DB::transaction(function () use ($tx, $reason) {
            $rev = $this->post($tx->account, 'reversal',
                $tx->direction === 'credit' ? 'debit' : 'credit',
                (float) $tx->amount,
                ['reference' => 'REV-' . $tx->reference, 'channel' => 'internal',
                 'description' => "Reversal of {$tx->reference}: $reason", 'allow_negative' => true]);
            $tx->update(['reversed' => true]);
            app(AccountingService::class)->reverseSource($tx, $reason);
            AuditLog::record('reversal', $tx, ['reason' => $reason]);
            return $rev;
        });
    }

    /** Balance of an account as at the end of a given date (computed from the ledger). */
    public function balanceAt(MemberAccount $account, $date): float
    {
        return (float) Transaction::where('member_account_id', $account->id)
            ->whereDate('txn_date', '<=', $date)
            ->selectRaw("COALESCE(SUM(CASE WHEN direction='credit' THEN amount ELSE -amount END),0) as b")
            ->value('b');
    }
}
