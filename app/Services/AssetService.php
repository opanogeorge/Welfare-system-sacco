<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\FixedAsset;
use App\Models\GlAccount;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AssetService
{
    public function __construct(private AccountingService $gl) {}

    public function register(array $data, ?GlAccount $paidFrom = null): FixedAsset
    {
        return DB::transaction(function () use ($data, $paidFrom) {
            $asset = FixedAsset::create($data);
            $this->gl->post($asset->purchase_date, "Asset purchase {$asset->code} {$asset->name}", [
                [$this->gl->gl('fixed_assets'), (float) $asset->cost, 0, $asset->name],
                [$paidFrom ?? $this->gl->gl('payables'), 0, (float) $asset->cost, $asset->name],
            ], $asset);
            return $asset;
        });
    }

    /** Post one month's depreciation for every active asset (idempotent per month). */
    public function depreciateMonth(Carbon $month): array
    {
        $end = $month->copy()->endOfMonth();
        $total = 0; $count = 0; $lines = [];
        foreach (FixedAsset::where('status', 'active')->whereDate('purchase_date', '<=', $end)->get() as $a) {
            if ($a->last_depreciated_on && $a->last_depreciated_on->gte($end)) continue;
            $amt = $a->monthlyDepreciation();
            if ($amt <= 0) continue;
            $a->update(['accumulated_depreciation' => (float) $a->accumulated_depreciation + $amt, 'last_depreciated_on' => $end]);
            $total += $amt; $count++;
        }
        if ($total > 0) {
            $this->gl->post($end, 'Depreciation ' . $end->format('M Y') . " ($count assets)", [
                [$this->gl->gl('depreciation_expense'), $total, 0],
                [$this->gl->gl('accumulated_depreciation'), 0, $total],
            ]);
        }
        return ['count' => $count, 'total' => round($total, 2)];
    }

    public function dispose(FixedAsset $a, float $proceeds, $date, GlAccount $receivedInto): void
    {
        if ($a->status !== 'active') throw new RuntimeException('Asset already disposed.');
        $nbv = $a->netBookValue();
        $gain = round($proceeds - $nbv, 2);
        DB::transaction(function () use ($a, $proceeds, $date, $receivedInto, $gain) {
            $this->gl->post($date, "Disposal of {$a->code} {$a->name}", [
                [$receivedInto, $proceeds, 0, 'Proceeds'],
                [$this->gl->gl('accumulated_depreciation'), (float) $a->accumulated_depreciation, 0],
                [$this->gl->gl('fixed_assets'), 0, (float) $a->cost],
                $gain >= 0 ? [$this->gl->gl('other_income'), 0, $gain, 'Gain on disposal'] : [$this->gl->gl('other_expense'), -$gain, 0, 'Loss on disposal'],
            ], $a);
            $a->update(['status' => 'disposed', 'disposed_on' => $date, 'disposal_amount' => $proceeds]);
            AuditLog::record('asset_disposed', $a, ['proceeds' => $proceeds, 'gain' => $gain]);
        });
    }
}
