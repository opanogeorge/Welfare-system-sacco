<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MessageLog extends Model
{
    protected $guarded = ['id'];

    protected $casts = [];

    public function member() { return $this->belongsTo(Member::class); }
    public function sender() { return $this->belongsTo(User::class, 'sent_by'); }
}
