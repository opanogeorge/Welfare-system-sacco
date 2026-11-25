<?php

namespace App\Console\Commands;

use App\Models\Loan;
use App\Services\CreditRiskService;
use Illuminate\Console\Command;

class RefreshRisk extends Command
{
    protected $signature = 'sacco:risk-refresh';
    protected $description = 'Update arrears history and re-score running loans';

    public function handle(CreditRiskService $risk): int
    {
        $n = $risk->refreshArrearsHistory();
        $scored = 0;
        if ($risk->activeModel()) {
            foreach (Loan::whereIn('status', ['pending', 'guarantors_pending', 'appraised', 'approved'])->whereNull('pd')->get() as $l) { $risk->scoreLoan($l); $scored++; }
        }
        $this->info("$n arrears records updated, $scored applications scored.");
        return self::SUCCESS;
    }
}
