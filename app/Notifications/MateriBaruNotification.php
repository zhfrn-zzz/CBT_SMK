<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Material;
use App\Services\SettingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MateriBaruNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Material $material) {}

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
            ->subject('Materi Baru: '.$this->material->title)
            ->greeting('Halo, '.$notifiable->name.'!')
            ->line("Materi baru \"{$this->material->title}\" telah dipublikasikan.")
            ->line('Pelajari materi baru ini sekarang!')
            ->action('Lihat Materi', url("/siswa/materi/{$this->material->id}"))
            ->salutation('Salam, '.config('app.name'));
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
