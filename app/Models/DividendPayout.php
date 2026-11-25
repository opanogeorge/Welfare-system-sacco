<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DividendPayout extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['paid' => 'boolean'];

    public function period() { return $this->belongsTo(DividendPeriod::class, 'dividend_period_id'); }
    public function member() { return $this->belongsTo(Member::class); }
}
