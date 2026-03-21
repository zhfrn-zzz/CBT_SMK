<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class CleanupCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int $deletedCount,
        private readonly int $freedBytes,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $freedFormatted = $this->formatBytes($this->freedBytes);

        return [
            'type' => 'cleanup_completed',
            'title' => 'Cleanup File Selesai',
            'message' => $this->deletedCount > 0
                ? "Berhasil menghapus {$this->deletedCount} file orphan ({$freedFormatted} dibebaskan)."
                : 'Tidak ditemukan file orphan. Storage sudah bersih.',
            'action_url' => '/admin/storage',
        ];
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        $value = (float) $bytes;
        while ($value >= 1024 && $i < count($units) - 1) {
            $value /= 1024;
            $i++;
        }

        return round($value, 1) . ' ' . $units[$i];
    }
}
