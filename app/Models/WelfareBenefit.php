<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WelfareBenefit extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['is_active' => 'boolean'];

    public static function amountFor(string $type, ?string $relationship): float
    {
        return (float) (static::where('is_active', true)->where('type', $type)->where('relationship', $relationship)->value('amount')
            ?? static::where('is_active', true)->where('type', $type)->whereNull('relationship')->value('amount') ?? 0);
    }
}
