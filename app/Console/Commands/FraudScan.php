<?php

namespace App\Console\Commands;

use App\Services\FraudMonitor;
use Illuminate\Console\Command;

class FraudScan extends Command
{
    protected $signature = 'sacco:fraud-scan';
    protected $description = 'Run nightly fraud and suspicious-activity rules';

    public function handle(FraudMonitor $f): int
    {
        $this->info($f->scan() . ' new alert(s).');
        return self::SUCCESS;
    }
}
