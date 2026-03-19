<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\ExamSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class UjianDijadwalkanNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly ExamSession $examSession) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'ujian_dijadwalkan',
            'title' => 'Ujian Baru Dijadwalkan',
            'message' => "Ujian \"{$this->examSession->name}\" telah dijadwalkan. Silakan cek jadwal ujian Anda.",
            'action_url' => '/siswa/ujian',
        ];
    }
}
