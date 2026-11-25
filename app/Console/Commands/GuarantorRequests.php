<?php

namespace App\Console\Commands;

use App\Services\GuarantorService;
use Illuminate\Console\Command;

class GuarantorRequests extends Command
{
    protected $signature = 'sacco:guarantor-requests';
    protected $description = 'Lapse unanswered guarantor requests and remind guarantors 2 days before they lapse';

    public function handle(GuarantorService $svc): int
    {
        $r = $svc->expireRequests();
        $this->info("Expired {$r['expired']}, reminded {$r['nudged']}.");
        return self::SUCCESS;
    }
}
