<?php

namespace App\Console\Commands;

use App\Support\Tenancy;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Console\Command;

class TenantsRun extends Command
{
    protected $signature = 'sacco:tenants-run {cmd : artisan command to run for every SACCO}';
    protected $description = 'Run an artisan command for the main SACCO and every tenant SACCO';

    public function handle(): int
    {
        foreach (Tenancy::all() as $t) {
            Tenancy::switchTo($t);
            $this->line('<info>' . ($t?->code ?? 'main') . ':</info> ' . $this->argument('cmd'));
            try {
                Artisan::call($this->argument('cmd'), [], $this->output);
            } catch (\Throwable $e) {
                $this->error($e->getMessage());
            }
        }
        Tenancy::switchTo(null);
        return self::SUCCESS;
    }
}
