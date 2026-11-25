<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountType extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'withdrawable'    => 'boolean',
        'counts_for_loan' => 'boolean',
        'earns_dividend'  => 'boolean',
        'earns_interest'  => 'boolean',
        'is_active'       => 'boolean',
    ];

    public function accounts()  { return $this->hasMany(MemberAccount::class); }
    public function glAccount() { return $this->belongsTo(GlAccount::class, 'gl_account_id'); }
}
