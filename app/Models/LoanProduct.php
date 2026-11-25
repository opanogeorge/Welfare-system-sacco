<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanProduct extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['is_active' => 'boolean', 'allow_top_up' => 'boolean', 'collateral_required' => 'boolean'];

    public function loans() { return $this->hasMany(Loan::class); }
}
