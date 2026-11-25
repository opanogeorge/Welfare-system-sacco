<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Usage: ->middleware('role:staff') any staff role (incl. custom roles), ->middleware('role:member').
 * Admin always passes. Feature access is checked by the 'permission' middleware.
 * Also enforces: active account, staff IP allowlist, password expiry.
 */
class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        $user = $request->user();
        if (! $user || ! $user->is_active) {
            auth()->logout();
            return redirect()->route('login')->withErrors(['login' => 'Your account is inactive.']);
        }

        if ($user->isStaff()) {
            $allowed = array_filter(array_map('trim', explode(',', (string) Setting::get('staff_allowed_ips', ''))));
            if ($allowed && ! IpUtils::checkIp($request->ip(), $allowed)) {
                abort(403, 'Staff access is not allowed from this network (' . $request->ip() . ').');
            }

            $days = (int) Setting::get('password_expiry_days', 0);
            $changed = $user->password_changed_at ?? $user->created_at;
            if ($days > 0 && $changed && $changed->lt(now()->subDays($days)) && ! $request->routeIs('profile.password', 'logout')) {
                return redirect()->route('profile.password')->with('warning', "Your password is older than $days days. Please change it to continue.");
            }
        }

        if (in_array('staff', $roles, true)) {
            if (! $user->isStaff()) abort(403, 'Staff area.');
            $roles = array_diff($roles, ['staff']);
            if (! $roles) return $next($request);
        }
        if ($roles && ! $user->hasRole(...$roles)) {
            abort(403, 'You do not have permission to perform this action.');
        }
        return $next($request);
    }
}
