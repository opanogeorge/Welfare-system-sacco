<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanSchedule extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['due_date' => 'date', 'paid_on' => 'date', 'penalty_applied_on' => 'date'];

    public function loan() { return $this->belongsTo(Loan::class); }

    public function balance(): float
    {
        return round(
            ((float) $this->principal_due - (float) $this->principal_paid)
            + ((float) $this->interest_due - (float) $this->interest_paid)
            + ((float) $this->penalty_due - (float) $this->penalty_paid),
            2
        );
    }
}
