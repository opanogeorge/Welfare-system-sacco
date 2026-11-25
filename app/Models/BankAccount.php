<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankAccount extends Model
{
    protected $guarded = ['id'];

    protected $casts = [];

    public function glAccount() { return $this->belongsTo(GlAccount::class, 'gl_account_id'); }
    public function reconciliations() { return $this->hasMany(BankReconciliation::class); }
}
