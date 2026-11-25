<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberCategory extends Model
{
    protected $guarded = ['id'];

    protected $casts = [];

    public function members() { return $this->hasMany(Member::class); }
}
