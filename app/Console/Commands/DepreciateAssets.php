<?php

namespace App\Console\Commands;

use App\Services\AssetService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class DepreciateAssets extends Command
{
    protected $signature = 'sacco:depreciate-assets {--month= : YYYY-MM, defaults to last month}';
    protected $description = 'Post monthly depreciation on fixed assets';

    public function handle(AssetService $s): int
    {
        $m = $this->option('month') ? Carbon::createFromFormat('Y-m', $this->option('month')) : now()->subMonthNoOverflow();
        $r = $s->depreciateMonth($m);
        $this->info("{$r['count']} asset(s), KES {$r['total']}");
        return self::SUCCESS;
    }
}
