<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

class ExportAuditLogJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly int $userId,
        private readonly array $filters = [],
    ) {}

    public function handle(): void
    {
        $query = AuditLog::query()->with('user:id,name,role');

        if (! empty($this->filters['user_id'])) {
            $query->where('user_id', $this->filters['user_id']);
        }
        if (! empty($this->filters['action'])) {
            $query->where('action', $this->filters['action']);
        }
        if (! empty($this->filters['auditable_type'])) {
            $query->where('auditable_type', $this->filters['auditable_type']);
        }
        if (! empty($this->filters['date_from'])) {
            $query->whereDate('created_at', '>=', $this->filters['date_from']);
        }
        if (! empty($this->filters['date_to'])) {
            $query->whereDate('created_at', '<=', $this->filters['date_to']);
        }

        $filename = 'exports/audit-log-'.now()->format('Y-m-d-His').'.csv';

        $handle = fopen('php://temp', 'r+');

        // Header
        fputcsv($handle, [
            'ID', 'User', 'Role', 'Action', 'Auditable Type', 'Auditable ID',
            'Description', 'IP Address', 'User Agent', 'Created At',
        ]);

        $query->orderByDesc('created_at')->chunk(500, function ($logs) use ($handle) {
            foreach ($logs as $log) {
                fputcsv($handle, [
                    $log->id,
                    $log->user?->name ?? 'System',
                    $log->user?->role?->value ?? '-',
                    $log->action,
                    $log->auditable_type ? class_basename($log->auditable_type) : '-',
                    $log->auditable_id ?? '-',
                    $log->description ?? '-',
                    $log->ip_address ?? '-',
                    $log->user_agent ?? '-',
                    $log->created_at?->toDateTimeString() ?? '-',
                ]);
            }
        });

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        Storage::disk('local')->put($filename, $csv);

        // Notify user that export is ready
        $user = User::find($this->userId);
        if ($user) {
            $user->notify(new \App\Notifications\AuditExportReady($filename));
        }
    }
}
