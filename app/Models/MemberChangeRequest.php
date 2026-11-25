<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberChangeRequest extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['changes' => 'array'];

    public function member() { return $this->belongsTo(Member::class); }
    public function processor() { return $this->belongsTo(User::class, 'processed_by'); }
}
