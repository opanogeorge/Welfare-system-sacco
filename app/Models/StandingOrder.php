<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StandingOrder extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['is_active' => 'boolean', 'last_run_on' => 'date'];

    public function member() { return $this->belongsTo(Member::class); }
    public function fromAccount() { return $this->belongsTo(MemberAccount::class, 'from_account_id'); }
    public function targetAccount() { return $this->belongsTo(MemberAccount::class, 'target_account_id'); }
    public function loan() { return $this->belongsTo(Loan::class); }
}
