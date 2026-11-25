<?php

namespace App\Console\Commands;

use App\Services\LoanService;
use Illuminate\Console\Command;

class RecoveryReview extends Command
{
    protected $signature = 'sacco:recovery-review';
    protected $description = 'Mark promises-to-pay as kept/broken and remind members of promises due tomorrow';

    public function handle(LoanService $loans): int
    {
        $r = $loans->reviewPromises();
        $this->info("{$r['kept']} kept, {$r['broken']} broken.");
        return self::SUCCESS;
    }
}
