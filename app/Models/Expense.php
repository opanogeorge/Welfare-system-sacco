<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    use BelongsToBranch;

    protected $guarded = ['id'];

    protected $casts = ['spent_on' => 'date'];

    public function recorder()  { return $this->belongsTo(User::class, 'recorded_by'); }
    public function glAccount() { return $this->belongsTo(GlAccount::class, 'gl_account_id'); }
    public function paidFrom()  { return $this->belongsTo(GlAccount::class, 'paid_from_gl_id'); }
}
