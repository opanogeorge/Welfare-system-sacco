<?php

namespace App\Http\Middleware;

use App\Support\Tenancy;
use Closure;
use Illuminate\Http\Request;

/** Picks the SACCO database from the domain (multi-SACCO mode). Runs before sessions start. */
class IdentifyTenant
{
    public function handle(Request $request, Closure $next)
    {
        if (Tenancy::enabled()) {
            Tenancy::switchTo(Tenancy::forHost($request->getHost()));
        }
        return $next($request);
    }
}
