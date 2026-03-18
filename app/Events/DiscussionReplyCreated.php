<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\DiscussionReply;
use App\Models\DiscussionThread;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DiscussionReplyCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public DiscussionReply $reply,
        public DiscussionThread $thread,
    ) {}
}
