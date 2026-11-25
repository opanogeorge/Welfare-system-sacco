<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanRepayment extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['paid_on' => 'date'];

    public function loan()   { return $this->belongsTo(Loan::class); }
    public function poster() { return $this->belongsTo(User::class, 'posted_by'); }
}
