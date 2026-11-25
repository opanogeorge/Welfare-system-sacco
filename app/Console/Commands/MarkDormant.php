<?php

namespace App\Console\Commands;

use App\Models\LoanSchedule;
use App\Models\Member;
use App\Models\Setting;
use App\Models\Transaction;
use App\Services\LoanService;
use App\Services\SmsService;
use Illuminate\Console\Command;

class MarkDormant extends Command
{
    protected $signature = 'sacco:mark-dormant';
    protected $description = 'Mark members with no deposits in N months (setting dormant_months) as dormant';

    public function handle(): int
    {
        $cutoff = today()->subMonths((int) Setting::get('dormant_months', 6));
        $n = Member::active()->whereDate('joined_on', '<', $cutoff)
            ->whereDoesntHave('transactions', fn ($q) => $q->where('type', 'deposit')->whereDate('txn_date', '>=', $cutoff))
            ->update(['status' => 'dormant']);
        $this->info("$n member(s) marked dormant.");
        return self::SUCCESS;
    }
}
