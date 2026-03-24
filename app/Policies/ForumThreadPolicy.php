<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ForumThread;
use App\Models\User;

class ForumThreadPolicy
{
    public function delete(User $user, ForumThread $thread): bool
    {
        return $user->isAdmin() || $user->isGuru() || $thread->user_id === $user->id;
    }

    public function pin(User $user, ForumThread $thread): bool
    {
        return $user->isAdmin() || $user->isGuru();
    }

    public function lock(User $user, ForumThread $thread): bool
    {
        return $user->isAdmin() || $user->isGuru();
    }
}
