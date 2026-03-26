<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\ExamSession;
use App\Services\SettingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NilaiDipublikasiNotification extends Notification implements ShouldQueue
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
            ->subject('Nilai Ujian Dipublikasikan')
            ->greeting('Halo, '.$notifiable->name.'!')
            ->line("Nilai ujian \"{$this->examSession->name}\" telah dipublikasikan.")
            ->line('Cek nilai Anda sekarang di aplikasi.')
            ->action('Lihat Nilai', url('/siswa/nilai'))
            ->salutation('Salam, '.config('app.name'));
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
