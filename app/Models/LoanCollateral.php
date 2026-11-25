<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanCollateral extends Model
{
    protected $guarded = ['id'];

    protected $casts = [];

    public function loan() { return $this->belongsTo(Loan::class); }
}
