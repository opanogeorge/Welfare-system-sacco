<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['is_hq' => 'boolean', 'is_active' => 'boolean'];

    public function members() { return $this->hasMany(Member::class); }
    public function users() { return $this->hasMany(User::class); }
}
