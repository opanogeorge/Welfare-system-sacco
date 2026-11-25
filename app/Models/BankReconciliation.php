<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankReconciliation extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['statement_date' => 'date'];

    public function bankAccount() { return $this->belongsTo(BankAccount::class); }
    public function user() { return $this->belongsTo(User::class, 'done_by'); }
}
