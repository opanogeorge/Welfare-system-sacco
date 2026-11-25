<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WelfareContribution extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['paid_at' => 'datetime'];

    public function event() { return $this->belongsTo(WelfareEvent::class, 'welfare_event_id'); }
    public function member() { return $this->belongsTo(Member::class); }
    public function poster() { return $this->belongsTo(User::class, 'posted_by'); }
}
