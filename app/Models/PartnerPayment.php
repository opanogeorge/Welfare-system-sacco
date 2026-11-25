<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartnerPayment extends Model
{
    protected $guarded = ['id'];

    protected $casts = [];

    public function client() { return $this->belongsTo(ApiClient::class, 'api_client_id'); }
}
