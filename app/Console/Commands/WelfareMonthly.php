<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\WelfareService;
use Illuminate\Console\Command;

class WelfareMonthly extends Command
{
    protected $signature = 'sacco:welfare-monthly {--force : Run even if today is not the set day}';
    protected $description = 'Charge the monthly welfare contribution (setting welfare_monthly_amount) and retry unpaid ones';

    public function handle(WelfareService $svc): int
    {
        $day = (int) Setting::get('welfare_monthly_day', 1);
        if (! $this->option('force') && today()->day !== max(1, min($day, today()->daysInMonth))) {
            $this->info('Not the welfare charge day.');
            return self::SUCCESS;
        }
        $r = $svc->chargeMonthly();
        $this->info("Charged {$r['charged']}, deducted {$r['deducted']}, left due {$r['due']}.");
        return self::SUCCESS;
    }
}
