<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrbCheck extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['response' => 'array', 'is_listed' => 'boolean', 'consent_obtained' => 'boolean'];

    public function member() { return $this->belongsTo(Member::class); }
    public function loan() { return $this->belongsTo(Loan::class); }
    public function checker() { return $this->belongsTo(User::class, 'checked_by'); }
}
