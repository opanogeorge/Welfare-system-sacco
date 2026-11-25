<?php

namespace App\Console\Commands;

use App\Services\ReminderService;
use Illuminate\Console\Command;

class SendReminders extends Command
{
    protected $signature = 'sacco:send-reminders {--dry-run : List what would be sent without sending}';
    protected $description = 'Send loan, contribution, promise-to-pay and welfare reminders from the reminder rules';

    public function handle(ReminderService $svc): int
    {
        ReminderService::seedDefaults();
        $r = $svc->run((bool) $this->option('dry-run'));
        foreach ($r['items'] as $i) $this->line("[{$i['rule']}] {$i['member']->member_no}: {$i['text']}");
        $this->info($this->option('dry-run') ? count($r['items']) . ' reminder(s) would be sent.' : "Sent {$r['sent']}, failed {$r['failed']}.");
        return self::SUCCESS;
    }
}
