<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class JournalEntry extends Model
{
    use BelongsToBranch;

    protected $guarded = ['id'];

    protected $casts = ['entry_date' => 'date', 'posted_at' => 'datetime', 'is_closing' => 'boolean'];

    public function lines()   { return $this->hasMany(JournalLine::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function poster()  { return $this->belongsTo(User::class, 'posted_by'); }
    public function source()  { return $this->morphTo(); }

    public function total(): float
    {
        return (float) $this->lines->sum('debit');
    }
}
