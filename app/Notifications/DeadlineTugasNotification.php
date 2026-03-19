<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Assignment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class DeadlineTugasNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Assignment $assignment) {}

    public function via(object $notifiable): array
    {
        return ['database'];
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
