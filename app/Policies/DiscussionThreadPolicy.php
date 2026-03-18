<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\DiscussionThread;
use App\Models\TeachingAssignment;
use App\Models\User;

class DiscussionThreadPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, DiscussionThread $thread): bool
    {
        return $this->isInClassroom($user, $thread);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, DiscussionThread $thread): bool
    {
        if ($user->id === $thread->user_id) {
            return true;
        }

        return $this->isTeacherOfClassroom($user, $thread);
    }

    public function delete(User $user, DiscussionThread $thread): bool
    {
        if ($user->id === $thread->user_id) {
            return true;
        }

        return $this->isTeacherOfClassroom($user, $thread);
    }

    public function pin(User $user, DiscussionThread $thread): bool
    {
        return $this->isTeacherOfClassroom($user, $thread);
    }

    public function lock(User $user, DiscussionThread $thread): bool
    {
        return $this->isTeacherOfClassroom($user, $thread);
    }

    public function reply(User $user, DiscussionThread $thread): bool
    {
        return $this->isInClassroom($user, $thread) && ! $thread->is_locked;
    }

    private function isInClassroom(User $user, DiscussionThread $thread): bool
    {
        if ($user->isGuru()) {
            return TeachingAssignment::where('user_id', $user->id)
                ->where('classroom_id', $thread->classroom_id)
                ->exists();
        }

        if ($user->isSiswa()) {
            return $user->classrooms()->where('classrooms.id', $thread->classroom_id)->exists();
        }

        return false;
    }

    private function isTeacherOfClassroom(User $user, DiscussionThread $thread): bool
    {
        if (! $user->isGuru()) {
            return false;
        }

        return TeachingAssignment::where('user_id', $user->id)
            ->where('classroom_id', $thread->classroom_id)
            ->exists();
    }
}
