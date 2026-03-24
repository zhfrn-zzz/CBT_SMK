<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneAuditLogs extends Command
{
    protected $signature = 'audit:prune {--days=90 : Number of days to retain}';

    protected $description = 'Delete audit log records older than the specified retention period';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoff = now()->subDays($days);

        $deleted = DB::table('audit_logs')
            ->where('created_at', '<', $cutoff)
            ->delete();

        $this->info("Pruned {$deleted} audit log(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
