<?php

namespace App\Console\Commands;

use App\Models\LoanSchedule;
use App\Models\Member;
use App\Models\Setting;
use App\Models\Transaction;
use App\Services\LoanService;
use App\Services\SmsService;
use Illuminate\Console\Command;

class ApplyPenalties extends Command
{
    protected $signature = 'sacco:apply-penalties';
    protected $description = 'Charge late-payment penalties on overdue loan installments';

    public function handle(LoanService $loans): int
    {
        $n = $loans->applyPenalties();
        $this->info("Processed $n overdue installment(s).");
        return self::SUCCESS;
    }
}
