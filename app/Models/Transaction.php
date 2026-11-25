<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use BelongsToBranch;

    protected $guarded = ['id'];

    protected $casts = ['txn_date' => 'date', 'amount' => 'decimal:2', 'balance_after' => 'decimal:2', 'reversed' => 'boolean'];

    public function member()  { return $this->belongsTo(Member::class); }
    public function account() { return $this->belongsTo(MemberAccount::class, 'member_account_id'); }
    public function loan()    { return $this->belongsTo(Loan::class); }
    public function poster()  { return $this->belongsTo(User::class, 'posted_by'); }
}
