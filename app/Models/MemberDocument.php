<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberDocument extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['verified' => 'boolean'];

    public function member() { return $this->belongsTo(Member::class); }
    public function uploader() { return $this->belongsTo(User::class, 'uploaded_by'); }
}
