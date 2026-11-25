<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Baseline security headers for cloud hosting. HSTS only when served over HTTPS. */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        $h = $response->headers;
        $h->set('X-Frame-Options', 'SAMEORIGIN');
        $h->set('X-Content-Type-Options', 'nosniff');
        $h->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $h->set('Permissions-Policy', 'camera=(self), microphone=(), geolocation=()');
        if ($request->isSecure()) $h->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        return $response;
    }
}
