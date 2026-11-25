<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['data' => 'array'];

    public function user() { return $this->belongsTo(User::class); }

    public static function record(string $action, ?Model $subject = null, array $data = []): void
    {
        static::create([
            'user_id'      => auth()->id(),
            'action'       => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id'   => $subject?->getKey(),
            'data'         => $data ?: null,
            'ip'           => request()?->ip(),
        ]);
    }
}
