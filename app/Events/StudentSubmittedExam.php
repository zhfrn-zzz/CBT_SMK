<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\ExamAttempt;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class StudentSubmittedExam implements ShouldBroadcast
{
    use InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly ExamAttempt $attempt,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('exam.' . $this->attempt->exam_session_id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'attempt_id' => $this->attempt->id,
            'user_id' => $this->attempt->user_id,
            'submitted_at' => $this->attempt->submitted_at?->toISOString(),
            'is_force_submitted' => $this->attempt->is_force_submitted,
            'score' => $this->attempt->score,
        ];
    }
}
