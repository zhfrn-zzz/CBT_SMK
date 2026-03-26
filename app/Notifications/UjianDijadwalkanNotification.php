<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\ExamSession;
use App\Services\SettingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UjianDijadwalkanNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly ExamSession $examSession) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (app(SettingService::class)->isSmtpConfigured()) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Ujian Baru Dijadwalkan')
            ->greeting('Halo, '.$notifiable->name.'!')
            ->line("Ujian \"{$this->examSession->name}\" telah dijadwalkan.")
            ->line('Silakan cek jadwal ujian Anda di aplikasi.')
            ->action('Lihat Jadwal Ujian', url('/siswa/ujian'))
            ->salutation('Salam, '.config('app.name'));
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
