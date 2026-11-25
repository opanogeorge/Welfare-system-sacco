<?php

namespace App\Http\Controllers;

use App\Models\ApiClient;
use App\Models\ApiLog;
use App\Models\AuditLog;
use App\Models\WebhookDelivery;
use App\Services\WebhookService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Admin > API & Webhooks: partner keys, scopes, IP allowlists, webhooks and logs. */
class ApiClientController extends Controller
{
    public function index()
    {
        return view('admin.api', [
            'clients' => ApiClient::withCount(['logs as calls_today' => fn ($q) => $q->where('created_at', '>=', today())])->latest()->get(),
            'deliveries' => WebhookDelivery::with('client')->latest()->take(25)->get(),
            'logs' => ApiLog::with('client')->latest('created_at')->take(25)->get(),
        ]);
    }

    public function store(Request $request)
    {
        $d = $this->validated($request);
        $key = ApiClient::generateKey();
        $secret = Str::random(40);
        $client = ApiClient::create($d + ['key_prefix' => $key['prefix'], 'key_hash' => $key['hash'],
            'webhook_secret' => Crypt::encryptString($secret), 'created_by' => auth()->id()]);
        AuditLog::record('api_client_created', $client);
        return back()->with('new_key', ['name' => $client->name, 'key' => $key['plain'], 'secret' => $secret]);
    }

    public function update(Request $request, ApiClient $client)
    {
        $client->update($this->validated($request, $client) + ['is_active' => $request->boolean('is_active')]);
        return back()->with('success', 'API client updated.');
    }

    public function regenerate(ApiClient $client)
    {
        $key = ApiClient::generateKey();
        $secret = Str::random(40);
        $client->update(['key_prefix' => $key['prefix'], 'key_hash' => $key['hash'], 'webhook_secret' => Crypt::encryptString($secret)]);
        AuditLog::record('api_key_regenerated', $client);
        return back()->with('new_key', ['name' => $client->name, 'key' => $key['plain'], 'secret' => $secret]);
    }

    public function testWebhook(ApiClient $client, WebhookService $hooks)
    {
        if (! $client->webhook_url) return back()->with('error', 'Set a webhook URL first.');
        $d = WebhookDelivery::create(['api_client_id' => $client->id, 'event' => 'ping', 'next_attempt_at' => now(),
            'payload' => ['id' => (string) Str::uuid(), 'event' => 'ping', 'created_at' => now()->toIso8601String(), 'data' => ['message' => 'Test from ' . sacco_name()]]]);
        $ok = $hooks->deliver($d);
        return back()->with($ok ? 'success' : 'error', $ok ? 'Webhook delivered (HTTP ' . $d->response_code . ').' : 'Webhook failed: HTTP ' . $d->response_code . ' ' . Str::limit($d->response_body, 150));
    }

    public function redeliver(WebhookDelivery $delivery, WebhookService $hooks)
    {
        $ok = $hooks->deliver($delivery);
        return back()->with($ok ? 'success' : 'error', $ok ? 'Delivered.' : 'Still failing (HTTP ' . $delivery->response_code . ').');
    }

    public function docs()
    {
        return view('admin.api-docs');
    }

    private function validated(Request $request, ?ApiClient $c = null): array
    {
        $d = $request->validate([
            'name' => 'required|string|max:120',
            'scopes' => 'required|array|min:1', 'scopes.*' => Rule::in(array_keys(ApiClient::SCOPES)),
            'allowed_ips' => 'nullable|string|max:500',
            'webhook_url' => 'nullable|url|starts_with:https://,http://localhost,http://127.0.0.1',
            'events' => 'nullable|array', 'events.*' => Rule::in(array_keys(ApiClient::EVENTS)),
        ]);
        $d['events'] = $d['events'] ?? [];
        return $d;
    }
}
