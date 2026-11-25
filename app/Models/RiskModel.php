<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RiskModel extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['features' => 'array', 'weights' => 'array', 'metrics' => 'array', 'is_active' => 'boolean'];

    public function trainer() { return $this->belongsTo(User::class, 'trained_by'); }
}
