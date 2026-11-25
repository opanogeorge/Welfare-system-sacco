<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['resolved_at' => 'datetime'];

    public function member() { return $this->belongsTo(Member::class); }
    public function assignee() { return $this->belongsTo(User::class, 'assigned_to'); }
}
