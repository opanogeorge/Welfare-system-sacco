<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CheckoffLine extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['posted' => 'boolean'];

    public function schedule() { return $this->belongsTo(CheckoffSchedule::class, 'checkoff_schedule_id'); }
    public function member() { return $this->belongsTo(Member::class); }
}
