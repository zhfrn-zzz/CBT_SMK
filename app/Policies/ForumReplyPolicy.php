<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ForumReply;
use App\Models\User;

class ForumReplyPolicy
{
    public function delete(User $user, ForumReply $reply): bool
    {
        return $user->isAdmin() || $user->isGuru() || $reply->user_id === $user->id;
    }
}
