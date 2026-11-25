<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Console\Command;

class TenantCreate extends Command
{
    protected $signature = 'sacco:tenant-create {code} {name} {domain} {--db= : database name (default sacco_<code>)} {--demo : load demo data}';
    protected $description = 'Create a new SACCO (multi-SACCO mode): database, tables, starting data';

    public function handle(): int
    {
        $code = strtolower($this->argument('code'));
        $db = $this->option('db') ?: 'sacco_' . preg_replace('/\W/', '_', $code);
        if (! preg_match('/^[A-Za-z0-9_]+$/', $db)) { $this->error('Invalid database name.'); return self::FAILURE; }
        DB::connection('landlord')->statement("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $tenant = Tenant::updateOrCreate(['code' => $code], ['name' => $this->argument('name'), 'domain' => strtolower($this->argument('domain')), 'database' => $db, 'is_active' => true]);
        Tenancy::switchTo($tenant);
        Artisan::call('migrate', ['--force' => true], $this->output);
        Artisan::call('db:seed', ['--force' => true], $this->output);
        \App\Models\Setting::put('sacco_name', $tenant->name);
        if ($this->option('demo')) Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true], $this->output);
        Tenancy::switchTo(null);
        $this->info("SACCO '{$tenant->name}' ready at https://{$tenant->domain}  (database $db, admin@sacco.local / Admin@123 - change it).");
        return self::SUCCESS;
    }
}
