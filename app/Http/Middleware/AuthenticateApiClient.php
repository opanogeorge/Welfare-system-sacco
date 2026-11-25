<?php

namespace App\Http\Middleware;

use App\Models\ApiClient;
use App\Models\ApiLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Partner API authentication: `Authorization: Bearer sk_...` (or `X-Api-Key: sk_...`).
 * Usage: ->middleware('api.client:payments.write')
 */
class AuthenticateApiClient
{
    public function handle(Request $request, Closure $next, string ...$scopes)
    {
        $start = microtime(true);
        $client = ApiClient::findByKey($request->bearerToken() ?: $request->header('X-Api-Key'));

        $deny = fn ($code, $msg) => response()->json(['error' => $msg], $code);
        if (! $client) $response = $deny(401, 'Invalid or missing API key.');
        elseif (! $client->ipAllowed($request->ip())) $response = $deny(403, 'This IP address is not allowed for this API key.');
        elseif ($missing = array_values(array_filter($scopes, fn ($s) => ! $client->can($s)))) $response = $deny(403, 'Missing scope: ' . implode(', ', $missing));
        elseif (RateLimiter::tooManyAttempts('api-client:' . $client->id, 120)) $response = $deny(429, 'Rate limit: 120 requests per minute.');
        else {
            RateLimiter::hit('api-client:' . $client->id, 60);
            $request->attributes->set('api_client', $client);
            $client->forceFill(['last_used_at' => now()])->saveQuietly();
            $response = $next($request);
        }

        try {
            ApiLog::create(['api_client_id' => $client?->id, 'method' => $request->method(), 'path' => substr($request->path(), 0, 250),
                'status' => $response->getStatusCode(), 'ip' => $request->ip(), 'duration_ms' => (int) ((microtime(true) - $start) * 1000), 'created_at' => now()]);
        } catch (\Throwable) {}

        return $response;
    }
}
