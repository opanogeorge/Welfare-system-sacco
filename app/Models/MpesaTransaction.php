<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MpesaTransaction extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['payload' => 'array', 'trans_time' => 'datetime'];
}
