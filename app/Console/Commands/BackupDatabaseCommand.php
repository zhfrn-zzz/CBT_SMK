<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

class BackupDatabaseCommand extends Command
{
    protected $signature = 'backup:database';

    protected $description = 'Create a MySQL database backup';

    public function handle(): int
    {
        $backupDir = storage_path('app/backups');
        if (! is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $date = now()->format('Y_m_d_His');
        $filename = "smk_lms_{$date}.sql.gz";
        $filepath = "{$backupDir}/{$filename}";

        $host = DB::getConfig('host');
        $port = DB::getConfig('port');
        $database = DB::getConfig('database');
        $username = DB::getConfig('username');
        $password = DB::getConfig('password');

        $command = ['mysqldump', '--host='.$host, '--port='.$port, '--user='.$username];
        if ($password) {
            $command[] = '--password='.$password;
        }
        $command[] = $database;

        $process = new Process($command);
        $process->setTimeout(3600);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->error('Backup failed: '.$process->getErrorOutput());

            return 1;
        }

        $gz = gzopen($filepath, 'wb9');
        gzwrite($gz, $process->getOutput());
        gzclose($gz);

        // Retain only last 30 backups
        $backups = glob("{$backupDir}/smk_lms_*.sql.gz");
        sort($backups);
        if (count($backups) > 30) {
            $toDelete = array_slice($backups, 0, count($backups) - 30);
            foreach ($toDelete as $file) {
                unlink($file);
            }
        }

        $this->info("Backup saved to: {$filename}");

        return 0;
    }
}
