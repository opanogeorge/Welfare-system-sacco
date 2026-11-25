<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FraudAlert extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['details' => 'array', 'reviewed_at' => 'datetime'];

    public function member() { return $this->belongsTo(Member::class); }
    public function transaction() { return $this->belongsTo(Transaction::class); }
    public function staff() { return $this->belongsTo(User::class, 'staff_id'); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewed_by'); }
}
