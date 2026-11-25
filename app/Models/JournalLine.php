<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JournalLine extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['reconciled_at' => 'datetime', 'debit' => 'decimal:2', 'credit' => 'decimal:2'];

    public function entry()   { return $this->belongsTo(JournalEntry::class, 'journal_entry_id'); }
    public function account() { return $this->belongsTo(GlAccount::class, 'gl_account_id'); }
    public function member()  { return $this->belongsTo(Member::class); }
}
