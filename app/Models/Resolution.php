<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Resolution extends Model
{
    protected $guarded = ['id'];

    protected $casts = [];

    public function meeting() { return $this->belongsTo(Meeting::class); }
}
