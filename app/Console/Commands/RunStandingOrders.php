<?php

namespace App\Console\Commands;

use App\Services\SavingsService;
use Illuminate\Console\Command;

class RunStandingOrders extends Command
{
    protected $signature = 'sacco:run-standing-orders';
    protected $description = 'Execute standing orders due today';

    public function handle(SavingsService $s): int
    {
        $r = $s->runStandingOrders();
        $this->info("{$r['done']} executed, {$r['failed']} failed.");
        return self::SUCCESS;
    }
}
