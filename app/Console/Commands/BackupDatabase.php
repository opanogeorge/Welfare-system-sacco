<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\File;
use Illuminate\Console\Command;

class BackupDatabase extends Command
{
    protected $signature = 'sacco:backup';
    protected $description = 'Dump the database to storage/app/backups and prune old backups';

    public function handle(): int
    {
        $dir = storage_path('app/backups');
        File::ensureDirectoryExists($dir);
        $file = $dir . '/sacco-' . now()->format('Ymd-His') . '.sql';
        $c = config('database.connections.' . config('database.default'));
        $cmd = sprintf('"%s" --single-transaction --routines -h%s -P%s -u%s %s %s > "%s"', config('sacco.backup.mysqldump'), $c['host'], $c['port'] ?? 3306, $c['username'],
            $c['password'] !== '' && $c['password'] !== null ? '-p' . escapeshellarg($c['password']) : '', escapeshellarg($c['database']), $file);
        exec($cmd, $out, $code);
        if ($code !== 0 || ! filesize($file)) { $this->error('Backup failed. Check MYSQLDUMP_PATH.'); return self::FAILURE; }
        foreach (File::files($dir) as $f) {
            if ($f->getMTime() < now()->subDays((int) config('sacco.backup.keep_days'))->timestamp) File::delete($f->getPathname());
        }
        $this->info('Backup saved: ' . $file);
        return self::SUCCESS;
    }
}
