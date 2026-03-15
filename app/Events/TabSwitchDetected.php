<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class TabSwitchDetected implements ShouldBroadcast
{
    use InteractsWithSockets;

    public function __construct(
        public readonly int $examSessionId,
        public readonly int $userId,
        public readonly string $userName,
        public readonly string $eventType,
        public readonly int $totalViolations,
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
            'user_name' => $this->userName,
            'event_type' => $this->eventType,
            'total_violations' => $this->totalViolations,
        ];
    }
}
