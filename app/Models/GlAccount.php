<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class GlAccount extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['is_active' => 'boolean', 'is_cash' => 'boolean'];

    public function parent() { return $this->belongsTo(self::class, 'parent_id'); }
    public function lines()  { return $this->hasMany(JournalLine::class); }

    /** Debit-normal accounts (assets, expenses) show balance as Dr - Cr. */
    public function isDebitNormal(): bool
    {
        return in_array($this->type, ['asset', 'expense'], true);
    }

    public static function bySystemKey(string $key): self
    {
        $id = Cache::rememberForever("gl_key_$key", fn () => static::where('system_key', $key)->value('id'));
        $account = $id ? static::find($id) : null;
        if ($account && $account->system_key === $key) return $account;

        Cache::forget("gl_key_$key"); // stale cache (e.g. after migrate:fresh)
        $account = static::where('system_key', $key)->first();
        if (! $account) throw new \RuntimeException("GL account with system key '$key' is not set up. Run the seeder or map it under Accounting > Chart of Accounts.");
        return $account;
    }

    /** Balance from posted lines (optionally between dates), signed by the account's normal side. */
    public function balance($from = null, $to = null): float
    {
        $q = JournalLine::where('gl_account_id', $this->id)
            ->whereHas('entry', function ($q) use ($from, $to) {
                $q->where('status', '!=', 'draft');
                if ($from) $q->whereDate('entry_date', '>=', $from);
                if ($to) $q->whereDate('entry_date', '<=', $to);
            });
        $dr = (float) (clone $q)->sum('debit');
        $cr = (float) (clone $q)->sum('credit');
        return round($this->isDebitNormal() ? $dr - $cr : $cr - $dr, 2);
    }

    protected static function booted(): void
    {
        static::saved(fn ($a) => $a->system_key && Cache::forget("gl_key_{$a->system_key}"));
    }
}
