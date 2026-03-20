<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ExportReadyNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $filename,
        private readonly string $path,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'export_ready',
            'title' => 'Export Siap Diunduh',
            'message' => 'File rapor nilai telah selesai dibuat.',
            'action_url' => '/admin/analytics/download-export/'.basename($this->filename),
            'filename' => $this->filename,
        ];
    }
}
