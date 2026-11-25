<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FixedAsset extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'purchase_date' => 'date', 'last_depreciated_on' => 'date',
        'next_maintenance_on' => 'date', 'disposed_on' => 'date',
    ];

    public function netBookValue(): float
    {
        return round((float) $this->cost - (float) $this->accumulated_depreciation, 2);
    }

    /** One month's depreciation, never taking NBV below salvage value. */
    public function monthlyDepreciation(): float
    {
        $depreciable = $this->netBookValue() - (float) $this->salvage_value;
        if ($depreciable <= 0 || $this->status !== 'active') return 0;

        $amount = $this->method === 'reducing_balance'
            ? $this->netBookValue() * ((float) $this->rate / 100) / 12
            : ((float) $this->cost - (float) $this->salvage_value) / max(1, $this->useful_life_years * 12);

        return round(min($amount, $depreciable), 2);
    }
}
