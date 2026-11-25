<?php

namespace App\Console\Commands;

use App\Services\CreditRiskService;
use Illuminate\Console\Command;

class TrainRiskModel extends Command
{
    protected $signature = 'sacco:train-risk-model';
    protected $description = 'Train the machine-learning credit risk model on loan history';

    public function handle(CreditRiskService $risk): int
    {
        try {
            $m = $risk->train();
            $this->info("Model {$m->version}: {$m->samples} loans, {$m->defaults} defaults, AUC " . ($m->auc ?? 'n/a'));
        } catch (\RuntimeException $e) {
            $this->warn($e->getMessage());
        }
        return self::SUCCESS;
    }
}
