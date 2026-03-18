<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Attendance;
use App\Models\TeachingAssignment;
use App\Models\User;

class AttendancePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isGuru();
    }

    public function update(User $user, Attendance $attendance): bool
    {
        return $user->isGuru() && $user->id === $attendance->user_id;
    }

    public function close(User $user, Attendance $attendance): bool
    {
        return $user->isGuru() && $user->id === $attendance->user_id;
    }

    public function export(User $user): bool
    {
        return $user->isGuru();
    }
}
