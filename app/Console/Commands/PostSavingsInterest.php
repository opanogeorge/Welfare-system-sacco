<?php

namespace App\Console\Commands;

use App\Services\SavingsService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class PostSavingsInterest extends Command
{
    protected $signature = 'sacco:post-savings-interest {--month= : YYYY-MM, defaults to last month}';
    protected $description = 'Credit monthly interest on interest-bearing savings accounts';

    public function handle(SavingsService $s): int
    {
        $m = $this->option('month') ? Carbon::createFromFormat('Y-m', $this->option('month')) : now()->subMonthNoOverflow();
        $this->info($s->postMonthlyInterest($m->startOfMonth()) . ' account(s) credited.');
        return self::SUCCESS;
    }
}
