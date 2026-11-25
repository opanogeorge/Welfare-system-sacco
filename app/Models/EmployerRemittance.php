<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployerRemittance extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['received_on' => 'date'];

    public function employer() { return $this->belongsTo(Employer::class); }
    public function schedule() { return $this->belongsTo(CheckoffSchedule::class, 'checkoff_schedule_id'); }
    public function bankAccount() { return $this->belongsTo(BankAccount::class); }
}
