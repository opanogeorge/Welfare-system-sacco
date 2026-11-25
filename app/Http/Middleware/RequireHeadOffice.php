<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Society-wide functions (GL, dividends, integrations, risk model) are for head-office staff only. */
class RequireHeadOffice
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless($request->user()?->isHq(), 403, 'This function is available to head-office staff only.');
        return $next($request);
    }
}
