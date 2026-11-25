<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DividendPeriod extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['paid_at' => 'datetime'];

    public function payouts() { return $this->hasMany(DividendPayout::class); }
}
