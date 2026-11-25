<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberAccount extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['balance' => 'decimal:2', 'frozen_amount' => 'decimal:2'];

    public function member()       { return $this->belongsTo(Member::class); }
    public function type()         { return $this->belongsTo(AccountType::class, 'account_type_id'); }
    public function transactions() { return $this->hasMany(Transaction::class); }

    public function availableBalance(): float
    {
        return max(0, (float) $this->balance - (float) $this->frozen_amount - (float) $this->type->min_balance);
    }
}
