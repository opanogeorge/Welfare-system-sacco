<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdentityVerification extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['details' => 'array'];

    public function member() { return $this->belongsTo(Member::class); }
    public function verifier() { return $this->belongsTo(User::class, 'verified_by'); }
}
