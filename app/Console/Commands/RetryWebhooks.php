<?php

namespace App\Console\Commands;

use App\Services\WebhookService;
use Illuminate\Console\Command;

class RetryWebhooks extends Command
{
    protected $signature = 'sacco:webhooks-retry';
    protected $description = 'Retry failed webhook deliveries that are due';

    public function handle(WebhookService $w): int
    {
        $this->info($w->retryDue() . ' delivery attempt(s).');
        return self::SUCCESS;
    }
}
