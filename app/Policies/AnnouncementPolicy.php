<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Announcement;
use App\Models\User;

class AnnouncementPolicy
{
    public function create(User $user): bool
    {
        return $user->isGuru() || $user->isAdmin();
    }

    public function update(User $user, Announcement $announcement): bool
    {
        return ($user->isGuru() || $user->isAdmin()) && $user->id === $announcement->user_id;
    }

    public function delete(User $user, Announcement $announcement): bool
    {
        return ($user->isGuru() || $user->isAdmin()) && $user->id === $announcement->user_id;
    }
}
