<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;

class TenantList extends Command
{
    protected $signature = 'sacco:tenant-list';
    protected $description = 'List SACCOs in multi-SACCO mode';

    public function handle(): int
    {
        $this->table(['Code', 'Name', 'Domain', 'Database', 'Active'], Tenant::orderBy('code')->get(['code', 'name', 'domain', 'database', 'is_active'])->toArray());
        return self::SUCCESS;
    }
}
