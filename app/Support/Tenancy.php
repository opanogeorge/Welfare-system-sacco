<?php

namespace App\Support;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Multi-SACCO mode: one installation, one database per SACCO, chosen by domain.
 * The main database holds the `tenants` table and is itself a SACCO (used for unknown domains).
 * Enable with MULTI_TENANT=true. Create SACCOs with:  php artisan sacco:tenant-create
 */
class Tenancy
{
    private static ?array $baseline = null;
    private static ?Tenant $current = null;

    public static function enabled(): bool
    {
        return (bool) config('sacco.multi_tenant');
    }

    public static function current(): ?Tenant
    {
        return self::$current;
    }

    public static function forHost(string $host): ?Tenant
    {
        try {
            return Tenant::where('domain', strtolower($host))->where('is_active', true)->first();
        } catch (\Throwable) {
            return null; // tenants table not migrated yet
        }
    }

    /** Point the app at a tenant's database (null = main database). */
    public static function switchTo(?Tenant $tenant): void
    {
        $conn = config('database.default');
        self::$baseline ??= [
            'db' => config("database.connections.$conn.database"), 'cache' => config('cache.prefix'),
            'disk' => config('filesystems.disks.local.root'), 'cookie' => config('session.cookie'),
            'sacco' => config('sacco'), 'mail' => config('mail'),
        ];
        $b = self::$baseline;
        config([
            "database.connections.$conn.database" => $tenant?->database ?? $b['db'],
            'cache.prefix' => $tenant ? 'sacco_' . $tenant->code . '_' : $b['cache'],
            'filesystems.disks.local.root' => $tenant ? storage_path('app/tenants/' . $tenant->code) : $b['disk'],
            'session.cookie' => $tenant ? 'sacco_' . $tenant->code . '_session' : $b['cookie'],
            'sacco' => array_merge($b['sacco'], $tenant ? ['name' => $tenant->name] : [], ['tenant' => $tenant?->code]),
            'mail' => $b['mail'],
        ]);
        DB::purge($conn);
        app('cache')->forgetDriver(config('cache.default'));
        Storage::forgetDisk('local');
        self::$current = $tenant;
        try { Integrations::apply(); } catch (\Throwable) {}
    }

    /** @return iterable<Tenant|null> null first = main database */
    public static function all(): iterable
    {
        yield null;
        foreach (Tenant::where('is_active', true)->orderBy('code')->get() as $t) yield $t;
    }
}
