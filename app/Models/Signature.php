<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Signature extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['signed_at' => 'datetime', 'otp_verified' => 'boolean'];

    public function signable() { return $this->morphTo(); }
    public function member() { return $this->belongsTo(Member::class); }
    public function user() { return $this->belongsTo(User::class); }
}
