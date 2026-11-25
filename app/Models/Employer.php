<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Employer extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['is_active' => 'boolean'];

    public function members() { return $this->hasMany(Member::class); }
    public function schedules() { return $this->hasMany(CheckoffSchedule::class); }
    public function remittances() { return $this->hasMany(EmployerRemittance::class); }
}
