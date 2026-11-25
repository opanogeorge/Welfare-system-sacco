<?php

namespace App\Console\Commands;

use App\Services\SavingsService;
use Illuminate\Console\Command;

class MatureFixedDeposits extends Command
{
    protected $signature = 'sacco:mature-fixed-deposits';
    protected $description = 'Pay out fixed deposits that have reached maturity';

    public function handle(SavingsService $s): int
    {
        $this->info($s->matureDue() . ' fixed deposit(s) matured.');
        return self::SUCCESS;
    }
}
