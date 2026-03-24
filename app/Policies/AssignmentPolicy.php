<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Assignment;
use App\Models\User;

class AssignmentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Assignment $assignment): bool
    {
        if ($user->isGuru()) {
            return $user->id === $assignment->user_id;
        }

        if ($user->isSiswa()) {
            return $user->classrooms()->where('classrooms.id', $assignment->classroom_id)->exists()
                && $assignment->is_published;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->isGuru();
    }

    public function update(User $user, Assignment $assignment): bool
    {
        return $user->isGuru()
            && $user->id === $assignment->user_id
            && $user->teachingClassrooms()->where('classrooms.id', $assignment->classroom_id)->exists();
    }

    public function delete(User $user, Assignment $assignment): bool
    {
        return $user->isGuru()
            && $user->id === $assignment->user_id
            && $user->teachingClassrooms()->where('classrooms.id', $assignment->classroom_id)->exists();
    }

    public function grade(User $user, Assignment $assignment): bool
    {
        return $user->isGuru()
            && $user->id === $assignment->user_id
            && $user->teachingClassrooms()->where('classrooms.id', $assignment->classroom_id)->exists();
    }
}
