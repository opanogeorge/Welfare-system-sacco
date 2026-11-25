<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A SACCO in multi-SACCO mode. Always read from the main ("landlord") connection. */
class Tenant extends Model
{
    protected $connection = 'landlord';

    protected $guarded = ['id'];

    protected $casts = ['is_active' => 'boolean'];
}
