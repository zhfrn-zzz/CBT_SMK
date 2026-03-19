<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Material;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class MateriBaruNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Material $material) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'materi_baru',
            'title' => 'Materi Baru Tersedia',
            'message' => "Materi baru \"{$this->material->title}\" telah dipublikasikan. Pelajari sekarang!",
            'action_url' => "/siswa/materi/{$this->material->id}",
        ];
    }
}
