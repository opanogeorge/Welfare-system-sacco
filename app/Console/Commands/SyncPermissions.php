<?php

namespace App\Console\Commands;

use App\Support\Permissions;
use Illuminate\Console\Command;

class SyncPermissions extends Command
{
    protected $signature = 'sacco:sync-permissions {--reset : Reset the built-in roles to their default permissions}';
    protected $description = 'Add new permissions from config/permissions.php (and optionally reset built-in roles)';

    public function handle(): int
    {
        if ($this->option('reset')) {
            foreach (\App\Models\Role::whereIn('name', array_keys(config('permissions.defaults')))->get() as $r) $r->permissions()->detach();
        }
        $added = Permissions::sync((bool) $this->option('reset'));
        $this->info($added ? 'Added: ' . implode(', ', $added) : 'Permissions are up to date.');
        return self::SUCCESS;
    }
}
