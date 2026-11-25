<?php

namespace App\Console\Commands;

use App\Models\LoanSchedule;
use App\Models\Member;
use App\Models\Setting;
use App\Models\Transaction;
use App\Services\LoanService;
use App\Services\SmsService;
use Illuminate\Console\Command;

class RepaymentReminders extends Command
{
    protected $signature = 'sacco:repayment-reminders {--days=3}';
    protected $description = 'SMS members whose installments fall due in N days';

    public function handle(SmsService $sms): int
    {
        $date = today()->addDays((int) $this->option('days'));
        $rows = LoanSchedule::with('loan.member')->whereDate('due_date', $date)
            ->whereIn('status', ['pending', 'partial'])
            ->whereHas('loan', fn ($q) => $q->where('status', 'disbursed'))->get();

        foreach ($rows as $s) {
            $sms->send($s->loan->member->phone, "Dear {$s->loan->member->first_name}, your loan {$s->loan->loan_no} installment of KES "
                . number_format($s->balance(), 2) . " is due on {$date->format('d/m/Y')}. Paybill " . config('sacco.mpesa.paybill')
                . ", Acc: {$s->loan->loan_no}.");
        }
        $this->info("Sent {$rows->count()} reminder(s).");
        return self::SUCCESS;
    }
}
