<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class ExamForceSubmitted implements ShouldBroadcast
{
    use InteractsWithSockets;

    public function __construct(
        public readonly int $examSessionId,
        public readonly int $userId,
        public readonly string $reason,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('exam.' . $this->examSessionId . '.student.' . $this->userId),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'reason' => $this->reason,
        ];
    }
}
