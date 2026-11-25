<?php

namespace App\Http\Middleware;

use App\Support\Permissions;
use Closure;
use Illuminate\Http\Request;

/**
 * ->middleware('permission')            uses the permission mapped to the current route (config/permissions.php)
 * ->middleware('permission:loans.approve,loans.reject')   any of the listed permissions
 */
class PermissionMiddleware
{
    public function handle(Request $request, Closure $next, string ...$perms)
    {
        $user = $request->user();
        if (! $user) return redirect()->route('login');
        if (! $perms && ($p = Permissions::forRoute($request->route()?->getName()))) $perms = [$p];
        if ($perms && ! $user->hasPermission(...$perms)) {
            abort(403, 'Your role does not include this permission. Ask the administrator to grant it under Administration → Roles & permissions.');
        }
        return $next($request);
    }
}
