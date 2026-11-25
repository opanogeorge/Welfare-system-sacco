<?php

namespace App\Services;

use App\Models\ApiClient;
use App\Models\WebhookDelivery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Outbound webhooks to partner systems. Each POST carries:
 *   X-Sacco-Event: loan.disbursed
 *   X-Sacco-Delivery: <id>
 *   X-Sacco-Signature: sha256=<HMAC-SHA256(raw body, client webhook secret)>
 * Sent after the database transaction commits; failures retry with back-off (sacco:webhooks-retry).
 */
class WebhookService
{
    public const MAX_ATTEMPTS = 6;

    public function dispatch(string $event, array $data): void
    {
        DB::afterCommit(function () use ($event, $data) {
            try {
                $clients = ApiClient::where('is_active', true)->whereNotNull('webhook_url')->get()
                    ->filter(fn ($c) => in_array($event, $c->events ?? [], true));
                foreach ($clients as $client) {
                    $d = WebhookDelivery::create([
                        'api_client_id' => $client->id, 'event' => $event,
                        'payload' => ['id' => (string) Str::uuid(), 'event' => $event, 'created_at' => now()->toIso8601String(), 'sacco' => config('sacco.name'), 'data' => $data],
                        'next_attempt_at' => now(),
                    ]);
                    $this->deliver($d);
                }
            } catch (\Throwable $e) {
                report($e);
            }
        });
    }

    public function deliver(WebhookDelivery $d): bool
    {
        $client = $d->client;
        $body = json_encode($d->payload, JSON_UNESCAPED_SLASHES);
        $sig = 'sha256=' . hash_hmac('sha256', $body, (string) $client->secret());
        $d->attempts++;
        try {
            $res = Http::timeout(5)->withHeaders([
                'X-Sacco-Event' => $d->event, 'X-Sacco-Delivery' => (string) $d->id, 'X-Sacco-Signature' => $sig, 'User-Agent' => 'SACCO-Webhooks/1.0',
            ])->withBody($body, 'application/json')->post($client->webhook_url);
            $d->response_code = $res->status();
            $d->response_body = Str::limit($res->body(), 1000);
            $ok = $res->successful();
        } catch (\Throwable $e) {
            $d->response_code = 0; $d->response_body = Str::limit($e->getMessage(), 1000); $ok = false;
        }
        if ($ok) {
            $d->delivered_at = now(); $d->next_attempt_at = null;
        } else {
            // 1m, 5m, 30m, 2h, 12h
            $d->next_attempt_at = $d->attempts >= self::MAX_ATTEMPTS ? null : now()->addMinutes([1, 5, 30, 120, 720][$d->attempts - 1] ?? 720);
        }
        $d->save();
        return $ok;
    }

    public function retryDue(): int
    {
        $n = 0;
        WebhookDelivery::whereNull('delivered_at')->whereNotNull('next_attempt_at')->where('next_attempt_at', '<=', now())
            ->with('client')->limit(200)->get()->each(function ($d) use (&$n) { if ($d->client?->is_active) { $this->deliver($d); $n++; } });
        return $n;
    }
}
