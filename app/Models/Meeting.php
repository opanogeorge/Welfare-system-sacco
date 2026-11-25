<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Meeting extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['scheduled_at' => 'datetime'];

    public function resolutions() { return $this->hasMany(Resolution::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
