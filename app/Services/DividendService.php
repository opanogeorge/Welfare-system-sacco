<?php

namespace App\Services;

use App\Models\AccountType;
use App\Models\AuditLog;
use App\Models\DividendPeriod;
use App\Models\Member;
use App\Models\MemberAccount;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Dividend on share capital = year-end share balance x dividend rate.
 * Interest on deposits     = average of the 12 month-end deposit balances x interest rate.
 * Withholding tax is deducted from the gross.
 */
class DividendService
{
    public function __construct(private AccountService $accounts) {}

    public function compute(DividendPeriod $period): DividendPeriod
    {
        if (in_array($period->status, ['approved', 'paid'], true)) throw new RuntimeException('Period already approved.');

        $shareTypes = AccountType::where('earns_dividend', true)->pluck('id');
        $depositTypes = AccountType::where('earns_interest', true)->pluck('id');
        $yearEnd = Carbon::create($period->year, 12, 31);

        DB::transaction(function () use ($period, $shareTypes, $depositTypes, $yearEnd) {
            $period->payouts()->delete();
            $totals = ['gross' => 0, 'tax' => 0, 'net' => 0];

            Member::whereIn('status', ['active', 'dormant'])->whereDate('joined_on', '<=', $yearEnd)
                ->with('accounts')->chunkById(200, function ($members) use ($period, $shareTypes, $depositTypes, $yearEnd, &$totals) {
                    foreach ($members as $m) {
                        $shares = $m->accounts->whereIn('account_type_id', $shareTypes)
                            ->sum(fn (MemberAccount $a) => $this->accounts->balanceAt($a, $yearEnd));

                        $avgDeposit = 0;
                        foreach ($m->accounts->whereIn('account_type_id', $depositTypes) as $a) {
                            $sum = 0;
                            for ($mo = 1; $mo <= 12; $mo++) {
                                $sum += max(0, $this->accounts->balanceAt($a, Carbon::create($period->year, $mo, 1)->endOfMonth()));
                            }
                            $avgDeposit += $sum / 12;
                        }

                        $div = round($shares * $period->dividend_rate / 100, 2);
                        $int = round($avgDeposit * $period->interest_rate / 100, 2);
                        $gross = $div + $int;
                        if ($gross <= 0) continue;
                        $tax = round($gross * $period->withholding_tax_rate / 100, 2);

                        $period->payouts()->create([
                            'member_id' => $m->id, 'share_balance' => $shares, 'avg_deposit_balance' => round($avgDeposit, 2),
                            'dividend_amount' => $div, 'interest_amount' => $int,
                            'gross' => $gross, 'tax' => $tax, 'net' => $gross - $tax,
                        ]);
                        $totals['gross'] += $gross; $totals['tax'] += $tax; $totals['net'] += $gross - $tax;
                    }
                });

            $period->update(['status' => 'computed', 'total_gross' => $totals['gross'], 'total_tax' => $totals['tax'], 'total_net' => $totals['net']]);
        });

        return $period->fresh();
    }

    public function pay(DividendPeriod $period): void
    {
        if ($period->status !== 'approved') throw new RuntimeException('Period must be approved before payment.');
        $code = $period->payout_destination === 'share_capital' ? 'SHR' : 'SAV';

        DB::transaction(function () use ($period, $code) {
            // GL: appropriate the gross out of surplus, recognise WHT payable; net flows via clearing to member accounts.
            $unpaid = $period->payouts()->where('paid', false);
            $gross = (float) (clone $unpaid)->sum('gross'); $tax = (float) (clone $unpaid)->sum('tax');
            $acc = app(AccountingService::class);
            if ($gross > 0) $acc->post(today(), "Dividends & interest on deposits {$period->year}", [
                [$acc->gl('dividend_appropriation'), $gross, 0, "Gross {$period->year}"],
                [$acc->gl('wht_payable'), 0, $tax, 'Withholding tax'],
                [$acc->gl('clearing'), 0, $gross - $tax, 'Net to members'],
            ], $period);

            foreach ($period->payouts()->where('paid', false)->with('member')->get() as $p) {
                $acc = $this->accounts->accountFor($p->member, $code);
                $this->accounts->post($acc, 'dividend', 'credit', (float) $p->net, [
                    'channel' => 'internal',
                    'description' => "Dividend & interest {$period->year} (net of WHT)",
                ]);
                $p->update(['paid' => true]);
            }
            $period->update(['status' => 'paid', 'paid_at' => now()]);
        });
        AuditLog::record('dividends_paid', $period);
    }
}
