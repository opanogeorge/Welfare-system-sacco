<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['is_system' => 'boolean'];

    public function permissions() { return $this->belongsToMany(Permission::class); }
    public function extraUsers()  { return $this->belongsToMany(User::class); }
    public function users()       { return $this->hasMany(User::class, 'role', 'name'); }

    public function isLocked(): bool { return in_array($this->name, ['admin', 'member'], true); }
}
