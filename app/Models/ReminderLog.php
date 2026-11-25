<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReminderLog extends Model
{
    protected $guarded = ['id'];

    public function rule() { return $this->belongsTo(ReminderRule::class, 'reminder_rule_id'); }
    public function member() { return $this->belongsTo(Member::class); }
    public function loan() { return $this->belongsTo(Loan::class); }
    public function sender() { return $this->belongsTo(User::class, 'sent_by'); }
}
