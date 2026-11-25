<?php

use Illuminate\Support\Facades\Schedule;

// Windows Task Scheduler / cron must run every minute:  php artisan schedule:run
// In multi-SACCO mode every job runs once per SACCO database.
$job = fn (string $cmd) => config('sacco.multi_tenant') ? "sacco:tenants-run \"$cmd\"" : $cmd;

Schedule::command($job('sacco:run-standing-orders'))->dailyAt('00:30');
Schedule::command($job('sacco:mature-fixed-deposits'))->dailyAt('00:45');
Schedule::command($job('sacco:apply-penalties'))->dailyAt('01:00');
Schedule::command($job('sacco:risk-refresh'))->dailyAt('01:30');
Schedule::command($job('sacco:fraud-scan'))->dailyAt('01:45');
Schedule::command($job('sacco:recovery-review'))->dailyAt('07:30');
Schedule::command('sacco:backup')->dailyAt('02:00');
// Reminder rules (Messaging → Automatic reminders) replace the old repayment-reminders / arrears-notices jobs
Schedule::command($job('sacco:send-reminders'))->dailyAt('08:00');
Schedule::command($job('sacco:welfare-monthly'))->dailyAt('06:00');
Schedule::command($job('sacco:guarantor-requests'))->dailyAt('07:45');
Schedule::command($job('sacco:webhooks-retry'))->everyFiveMinutes();
Schedule::command($job('sacco:post-savings-interest'))->monthlyOn(1, '03:00');
Schedule::command($job('sacco:depreciate-assets'))->monthlyOn(1, '03:30');
Schedule::command($job('sacco:mark-dormant'))->monthlyOn(1, '04:00');
Schedule::command($job('sacco:train-risk-model'))->monthlyOn(2, '02:30');
