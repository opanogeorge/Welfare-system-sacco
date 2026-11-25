<?php

namespace App\Services;

use App\Models\AccountType;
use App\Models\AuditLog;
use App\Models\FixedDeposit;
use App\Models\Member;
use App\Models\MemberAccount;
use App\Models\Setting;
use App\Models\StandingOrder;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SavingsService
{
    public function __construct(private AccountService $accounts, private LoanService $loans, private AccountingService $gl, private SmsService $sms) {}

    /* ---------------- Fixed deposits ---------------- */

    public function openFixedDeposit(Member $member, float $amount, float $rate, int $months, bool $rollover = false): FixedDeposit
    {
        $source = $this->accounts->accountFor($member, 'SAV')->load('type');
        if ($amount > $source->availableBalance()) throw new RuntimeException('Insufficient available savings to fund this fixed deposit.');

        return DB::transaction(function () use ($member, $amount, $rate, $months, $rollover, $source) {
            $interest = round($amount * $rate / 100 * $months / 12, 2);
            $fd = FixedDeposit::create([
                'fd_no' => 'FD' . now()->format('ym') . str_pad((string) (FixedDeposit::count() + 1), 4, '0', STR_PAD_LEFT),
                'member_id' => $member->id, 'amount' => $amount, 'rate' => $rate, 'term_months' => $months,
                'start_date' => today(), 'maturity_date' => today()->addMonthsNoOverflow($months),
                'interest_amount' => $interest, 'auto_rollover' => $rollover, 'created_by' => auth()->id(),
            ]);
            $this->accounts->post($source, 'transfer_out', 'debit', $amount, ['channel' => 'internal', 'description' => "Placed in fixed deposit {$fd->fd_no}"]);
            $this->gl->post(today(), "Fixed deposit {$fd->fd_no} placed", [
                [$this->gl->gl('clearing'), $amount, 0, $fd->fd_no, $member->id],
                [$this->gl->gl('fixed_deposits'), 0, $amount, $fd->fd_no, $member->id],
            ], $fd);
            AuditLog::record('fd_opened', $fd);
            return $fd;
        });
    }

    /** Pay out principal + interest (less WHT) to savings. $break = early withdrawal (interest forfeited per setting). */
    public function closeFixedDeposit(FixedDeposit $fd, bool $break = false): void
    {
        if ($fd->status !== 'active') throw new RuntimeException('Fixed deposit is not active.');
        $interest = (float) $fd->interest_amount;
        if ($break) {
            $elapsed = max(0, $fd->start_date->diffInMonths(today()));
            $interest = round((float) $fd->amount * ((float) $fd->rate * (float) Setting::get('fd_break_rate_factor', 0.5)) / 100 * $elapsed / 12, 2);
        }
        $wht = round($interest * (float) Setting::get('fd_wht_rate', 15) / 100, 2);
        $net = round((float) $fd->amount + $interest - $wht, 2);

        DB::transaction(function () use ($fd, $break, $interest, $wht, $net) {
            $this->gl->post(today(), "Fixed deposit {$fd->fd_no} " . ($break ? 'broken' : 'matured'), [
                [$this->gl->gl('fixed_deposits'), (float) $fd->amount, 0, $fd->fd_no, $fd->member_id],
                [$this->gl->gl('savings_interest_expense'), $interest, 0, 'FD interest', $fd->member_id],
                [$this->gl->gl('wht_payable'), 0, $wht, 'WHT on FD interest', $fd->member_id],
                [$this->gl->gl('clearing'), 0, $net, $fd->fd_no, $fd->member_id],
            ], $fd);
            $this->accounts->post($this->accounts->accountFor($fd->member, 'SAV'), 'transfer_in', 'credit', $net,
                ['channel' => 'internal', 'description' => "Fixed deposit {$fd->fd_no} payout (interest " . number_format($interest, 2) . ', WHT ' . number_format($wht, 2) . ')']);
            $fd->update(['status' => $break ? 'broken' : 'matured', 'closed_on' => today(), 'interest_amount' => $interest, 'wht' => $wht]);
        });
        $this->sms->send($fd->member->phone, "Your fixed deposit {$fd->fd_no} has " . ($break ? 'been closed early' : 'matured') . '. KES ' . number_format($net, 2) . ' credited to your savings.', $fd->member_id);

        if (! $break && $fd->auto_rollover) {
            $this->openFixedDeposit($fd->member, (float) $fd->amount, (float) $fd->rate, $fd->term_months, true);
        }
    }

    public function matureDue(): int
    {
        $n = 0;
        foreach (FixedDeposit::where('status', 'active')->whereDate('maturity_date', '<=', today())->get() as $fd) {
            $this->closeFixedDeposit($fd);
            $n++;
        }
        return $n;
    }

    /* ---------------- Savings interest ---------------- */

    /** Credit monthly interest on accounts whose type has an interest rate, on the month-end balance. */
    public function postMonthlyInterest(Carbon $month): int
    {
        $tag = 'INT-' . $month->format('Ym');
        $end = $month->copy()->endOfMonth();
        $n = 0;
        foreach (AccountType::where('interest_rate', '>', 0)->where('earns_interest', false)->get() as $type) {
            MemberAccount::where('account_type_id', $type->id)->with('member')->chunkById(200, function ($accounts) use ($type, $tag, $end, &$n) {
                foreach ($accounts as $a) {
                    if (Transaction::where('member_account_id', $a->id)->where('external_ref', $tag)->exists()) continue;
                    $bal = $this->accounts->balanceAt($a, $end);
                    $interest = round(max(0, $bal - (float) $type->min_balance) * (float) $type->interest_rate / 1200, 2);
                    if ($interest < 1) continue;
                    $this->accounts->post($a, 'interest', 'credit', $interest, ['channel' => 'internal', 'external_ref' => $tag,
                        'date' => $end, 'description' => "Interest {$end->format('M Y')} @ {$type->interest_rate}% p.a."]);
                    $n++;
                }
            });
        }
        return $n;
    }

    /* ---------------- Standing orders ---------------- */

    public function runStandingOrders(?Carbon $date = null): array
    {
        $date = $date ?? today();
        $done = 0; $failed = 0;
        $orders = StandingOrder::with('fromAccount.type', 'member', 'loan', 'targetAccount')->where('is_active', true)
            ->where('day_of_month', $date->day)
            ->where(fn ($q) => $q->whereNull('last_run_on')->orWhere('last_run_on', '<', $date->copy()->startOfMonth()))->get();

        foreach ($orders as $so) {
            try {
                DB::transaction(function () use ($so, $date) {
                    $amount = (float) $so->amount;
                    if ($so->target_type === 'loan') {
                        if (! $so->loan || $so->loan->status !== 'disbursed') { $so->update(['is_active' => false, 'last_result' => 'Loan cleared - order stopped']); return; }
                        $amount = min($amount, $so->loan->outstanding);
                    }
                    if ($amount > $so->fromAccount->availableBalance()) throw new RuntimeException('Insufficient funds');
                    $this->accounts->post($so->fromAccount, 'transfer_out', 'debit', $amount, ['channel' => 'internal', 'date' => $date, 'description' => "Standing order #{$so->id}"]);
                    if ($so->target_type === 'loan') {
                        $this->loans->repay($so->loan, $amount, 'internal', "SO{$so->id}", $date);
                    } else {
                        $this->accounts->post($so->targetAccount, 'transfer_in', 'credit', $amount, ['channel' => 'internal', 'date' => $date, 'description' => "Standing order #{$so->id}"]);
                    }
                    $so->update(['last_run_on' => $date, 'last_result' => 'OK ' . number_format($amount, 2)]);
                });
                $done++;
            } catch (\Throwable $e) {
                $so->update(['last_run_on' => $date, 'last_result' => 'Failed: ' . $e->getMessage()]);
                $this->sms->send($so->member->phone, "Standing order of KES " . number_format($so->amount, 2) . " could not run: {$e->getMessage()}. Please top up your savings.", $so->member_id);
                $failed++;
            }
        }
        return ['done' => $done, 'failed' => $failed];
    }

    /* ---------------- Contribution arrears ---------------- */

    /** Expected vs actual deposit contributions over the last N months for each active member. */
    public function contributionArrears(int $months = 6)
    {
        $from = today()->subMonthsNoOverflow($months - 1)->startOfMonth();
        $depType = AccountType::where('code', 'DEP')->value('id');

        return Member::active()->where('monthly_contribution', '>', 0)->orderBy('member_no')->get()->map(function ($m) use ($from, $months, $depType) {
            $start = $m->joined_on->gt($from) ? $m->joined_on->copy()->startOfMonth() : $from;
            $n = max(0, min($months, $start->diffInMonths(today()->endOfMonth()) + 1));
            $expected = round((float) $m->monthly_contribution * $n, 2);
            $actual = (float) Transaction::where('member_id', $m->id)->where('type', 'deposit')
                ->whereHas('account', fn ($q) => $q->where('account_type_id', $depType))
                ->whereDate('txn_date', '>=', $start)->sum('amount');
            return ['member' => $m, 'months' => $n, 'expected' => $expected, 'actual' => $actual, 'arrears' => max(0, round($expected - $actual, 2))];
        });
    }
}
