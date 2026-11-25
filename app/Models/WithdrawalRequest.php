<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WithdrawalRequest extends Model
{
    protected $guarded = ['id'];

    protected $casts = [];

    public function member() { return $this->belongsTo(Member::class); }
    public function processor() { return $this->belongsTo(User::class, 'processed_by'); }
}
