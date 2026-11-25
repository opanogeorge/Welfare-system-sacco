<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommitteeMember extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['from_date' => 'date', 'to_date' => 'date'];

    public function member() { return $this->belongsTo(Member::class); }
}
