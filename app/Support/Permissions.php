<?php

namespace App\Support;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/** Permission catalogue (config/permissions.php), route → permission lookup and role permission cache. */
class Permissions
{
    public static function catalogue(): array
    {
        return config('permissions.permissions', []);
    }

    public static function exists(string $name): bool
    {
        foreach (self::catalogue() as $items) if (isset($items[$name])) return true;
        return false;
    }

    /** Permission required for a route name (first matching pattern), or null if any staff user may open it. */
    public static function forRoute(?string $route): ?string
    {
        if (! $route) return null;
        static $memo = [];
        if (array_key_exists($route, $memo)) return $memo[$route];
        foreach (config('permissions.routes', []) as $pattern => $perm) {
            if ($pattern === $route || (str_contains($pattern, '*') && fnmatch($pattern, $route))) return $memo[$route] = $perm;
        }
        return $memo[$route] = null;
    }

    /** role name => [permission names], cached until roles change. */
    public static function map(): array
    {
        return Cache::rememberForever('role_permissions', function () {
            if (! Schema::hasTable('roles')) return self::fallback();
            return Role::with('permissions:id,name')->get()->mapWithKeys(fn ($r) => [$r->name => $r->permissions->pluck('name')->all()])->all();
        });
    }

    public static function flush(): void
    {
        Cache::forget('role_permissions');
    }

    /** Before the migration has run, behave like the original fixed roles. */
    private static function fallback(): array
    {
        return config('permissions.defaults', []);
    }

    /**
     * Create missing permissions from the config. With $seedDefaults, also give the built-in
     * roles their default permissions (first install). Never removes grants an admin made.
     */
    public static function sync(bool $seedDefaults = false): array
    {
        $added = [];
        foreach (self::catalogue() as $group => $items) {
            foreach ($items as $name => $label) {
                $p = Permission::firstOrNew(['name' => $name]);
                if (! $p->exists) $added[] = $name;
                $p->fill(['label' => $label, 'group' => $group])->save();
            }
        }
        $known = collect(self::catalogue())->flatMap(fn ($i) => array_keys($i))->all();
        Permission::whereNotIn('name', $known)->delete();

        $defaults = config('permissions.defaults', []);
        foreach (Role::all() as $role) {
            if ($role->name === 'admin') { $role->permissions()->sync(Permission::pluck('id')); continue; }
            $want = $defaults[$role->name] ?? [];
            // first install: everything; later syncs: only permissions that are new to the catalogue
            $give = $seedDefaults ? $want : array_intersect($want, $added);
            if ($give) $role->permissions()->syncWithoutDetaching(Permission::whereIn('name', $give)->pluck('id'));
        }
        self::flush();
        return $added;
    }
}
