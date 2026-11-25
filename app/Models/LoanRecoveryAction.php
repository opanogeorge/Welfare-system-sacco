<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanRecoveryAction extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['promise_date' => 'date'];

    public function loan() { return $this->belongsTo(Loan::class); }
    public function user() { return $this->belongsTo(User::class); }
}
