<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Announcement;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class PengumumanBaruNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Announcement $announcement) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'pengumuman_baru',
            'title' => 'Pengumuman Baru',
            'message' => "Ada pengumuman baru: \"{$this->announcement->title}\". Klik untuk melihat detail.",
            'action_url' => "/siswa/pengumuman/{$this->announcement->id}",
        ];
    }
}
