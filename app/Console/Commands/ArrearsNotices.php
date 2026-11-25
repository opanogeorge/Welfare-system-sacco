<?php

namespace App\Console\Commands;

use App\Models\Loan;
use App\Services\NotificationService;
use App\Services\SavingsService;
use Illuminate\Console\Command;

class ArrearsNotices extends Command
{
    protected $signature = 'sacco:arrears-notices';
    protected $description = 'SMS members with loans in arrears and contribution arrears';

    public function handle(NotificationService $n, SavingsService $s): int
    {
        $c = 0;
        foreach (Loan::with('member')->where('status', 'disbursed')->get() as $loan) {
            if (($d = $loan->daysInArrears()) > 0) {
                $n->toMember($loan->member, "Dear {first_name}, loan {$loan->loan_no} is {$d} days in arrears (KES " . number_format($loan->arrears(), 2) . '). Please pay via Paybill ' . config('sacco.mpesa.paybill') . ", Acc {$loan->loan_no}.");
                $c++;
            }
        }
        foreach ($s->contributionArrears(3)->where('arrears', '>', 0) as $r) {
            $n->toMember($r['member'], 'Dear {first_name}, your deposit contributions are KES ' . number_format($r['arrears'], 2) . ' behind. Paybill ' . config('sacco.mpesa.paybill') . ', Acc {member_no}.');
            $c++;
        }
        $this->info("$c notice(s) sent.");
        return self::SUCCESS;
    }
}
