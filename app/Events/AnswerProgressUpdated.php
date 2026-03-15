<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class AnswerProgressUpdated implements ShouldBroadcast
{
    use InteractsWithSockets;

    public function __construct(
        public readonly int $examSessionId,
        public readonly int $userId,
        public readonly int $answeredCount,
        public readonly int $totalQuestions,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('exam.' . $this->examSessionId),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->userId,
            'answered_count' => $this->answeredCount,
            'total_questions' => $this->totalQuestions,
        ];
    }
}
