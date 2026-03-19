<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\ExamSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class NilaiDipublikasiNotification extends Notification implements ShouldQueue
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
            'type' => 'nilai_dipublikasi',
            'title' => 'Nilai Ujian Dipublikasikan',
            'message' => "Nilai ujian \"{$this->examSession->name}\" telah dipublikasikan. Cek nilai Anda sekarang.",
            'action_url' => '/siswa/nilai',
        ];
    }
}
