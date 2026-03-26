<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Announcement;
use App\Services\SettingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PengumumanBaruNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Announcement $announcement) {}

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
            ->subject('Pengumuman Baru: '.$this->announcement->title)
            ->greeting('Halo, '.$notifiable->name.'!')
            ->line("Ada pengumuman baru: \"{$this->announcement->title}\".")
            ->line('Klik tombol di bawah untuk melihat detail pengumuman.')
            ->action('Lihat Pengumuman', url("/siswa/pengumuman/{$this->announcement->id}"))
            ->salutation('Salam, '.config('app.name'));
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
