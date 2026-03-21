<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;

class CleanupAuditLogsCommand extends Command
{
    protected $signature = 'audit:cleanup {--days= : Retention period in days}';

    protected $description = 'Cleanup audit logs older than retention period';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config('audit.retention_days', 365));

        $cutoff = now()->subDays($days);

        $count = AuditLog::where('created_at', '<', $cutoff)->count();

        if ($count === 0) {
            $this->info('No audit logs to cleanup.');

            return self::SUCCESS;
        }

        $this->info("Deleting {$count} audit logs older than {$days} days...");

        // Delete in chunks to avoid memory issues
        $deleted = 0;
        do {
            $batch = AuditLog::where('created_at', '<', $cutoff)
                ->limit(1000)
                ->delete();
            $deleted += $batch;
        } while ($batch > 0);

        $this->info("Deleted {$deleted} audit logs.");

        return self::SUCCESS;
    }
}
