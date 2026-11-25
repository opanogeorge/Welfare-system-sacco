<?php

namespace App\Services;

use Carbon\Carbon;

class LoanCalculator
{
    /**
     * Build a monthly amortisation schedule.
     *
     * @param  float   $principal
     * @param  float   $rate      annual interest rate in %
     * @param  int     $months
     * @param  string  $method    flat | reducing
     * @return array{rows: array<int, array>, total_interest: float, total_payable: float, installment: float}
     */
    public static function schedule(float $principal, float $rate, int $months, string $method, ?Carbon $firstDue = null): array
    {
        $firstDue = $firstDue ?? today()->addMonthNoOverflow();
        $rows = [];
        $balance = $principal;

        if ($method === 'flat') {
            $totalInterest = round($principal * ($rate / 100) * ($months / 12), 2);
            $p = round($principal / $months, 2);
            $i = round($totalInterest / $months, 2);
            for ($n = 1; $n <= $months; $n++) {
                $last = $n === $months;
                $pd = $last ? round($balance, 2) : $p;
                $id = $last ? round($totalInterest - $i * ($months - 1), 2) : $i;
                $rows[] = self::row($n, $firstDue->copy()->addMonthsNoOverflow($n - 1), $balance, $pd, $id);
                $balance -= $pd;
            }
        } elseif ($method === 'equal_principal') {
            // Same principal every month; interest on the falling balance, so installments decline.
            $r = $rate / 1200;
            $p = round($principal / $months, 2);
            for ($n = 1; $n <= $months; $n++) {
                $id = round($balance * $r, 2);
                $pd = $n === $months ? round($balance, 2) : $p;
                $rows[] = self::row($n, $firstDue->copy()->addMonthsNoOverflow($n - 1), $balance, $pd, $id);
                $balance -= $pd;
            }
        } else {
            $r = $rate / 1200;
            $emi = $r > 0 ? $principal * $r / (1 - pow(1 + $r, -$months)) : $principal / $months;
            $emi = round($emi, 2);
            for ($n = 1; $n <= $months; $n++) {
                $id = round($balance * $r, 2);
                $pd = $n === $months ? round($balance, 2) : round($emi - $id, 2);
                $rows[] = self::row($n, $firstDue->copy()->addMonthsNoOverflow($n - 1), $balance, $pd, $id);
                $balance -= $pd;
            }
        }

        $ti = round(array_sum(array_column($rows, 'interest_due')), 2);

        return [
            'rows'           => $rows,
            'total_interest' => $ti,
            'total_payable'  => round($principal + $ti, 2),
            'installment'    => $rows[0]['total_due'] ?? 0,
            'last_installment' => end($rows)['total_due'] ?? 0,
        ];
    }

    private static function row(int $n, Carbon $due, float $opening, float $p, float $i): array
    {
        return [
            'installment_no'  => $n,
            'due_date'        => $due->toDateString(),
            'opening_balance' => round($opening, 2),
            'principal_due'   => $p,
            'interest_due'    => $i,
            'total_due'       => round($p + $i, 2),
            'closing_balance' => round(max(0, $opening - $p), 2),
        ];
    }
}
