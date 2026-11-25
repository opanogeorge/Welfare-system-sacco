<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FixedDeposit extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['start_date' => 'date', 'maturity_date' => 'date', 'closed_on' => 'date', 'auto_rollover' => 'boolean'];

    public function member() { return $this->belongsTo(Member::class); }
}
