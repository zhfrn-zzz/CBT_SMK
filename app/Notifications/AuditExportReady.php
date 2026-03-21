<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class AuditExportReady extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $filename,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'export_ready',
            'title' => 'Export Audit Log Siap',
            'message' => 'File export audit log telah selesai dibuat.',
            'action_url' => '/admin/audit-log/download-export/'.basename($this->filename),
            'filename' => basename($this->filename),
        ];
    }
}
