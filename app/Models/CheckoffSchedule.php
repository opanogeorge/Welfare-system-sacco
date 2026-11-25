<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CheckoffSchedule extends Model
{
    protected $guarded = ['id'];

    protected $casts = [];

    public function employer() { return $this->belongsTo(Employer::class); }
    public function lines() { return $this->hasMany(CheckoffLine::class); }
    public function remittances() { return $this->hasMany(EmployerRemittance::class); }
}
