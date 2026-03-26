<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Assignment;
use App\Services\SettingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DeadlineTugasNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Assignment $assignment) {}

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
            ->subject('Deadline Tugas Mendekat')
            ->greeting('Halo, '.$notifiable->name.'!')
            ->line("Tugas \"{$this->assignment->title}\" memiliki deadline dalam 24 jam.")
            ->line('Segera kumpulkan tugasmu sebelum terlambat!')
            ->action('Lihat Tugas', url("/siswa/tugas/{$this->assignment->id}"))
            ->salutation('Salam, '.config('app.name'));
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'deadline_tugas',
            'title' => 'Deadline Tugas Mendekat',
            'message' => "Tugas \"{$this->assignment->title}\" memiliki deadline dalam 24 jam. Segera kumpulkan tugasmu!",
            'action_url' => "/siswa/tugas/{$this->assignment->id}",
        ];
    }
}
