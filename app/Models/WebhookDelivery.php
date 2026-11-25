<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookDelivery extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['payload' => 'array', 'next_attempt_at' => 'datetime', 'delivered_at' => 'datetime'];

    public function client() { return $this->belongsTo(ApiClient::class, 'api_client_id'); }
}
