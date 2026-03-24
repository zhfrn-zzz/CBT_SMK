<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ExamSession;
use App\Models\User;

class ExamSessionPolicy
{
    public function view(User $user, ExamSession $examSession): bool
    {
        return $user->isGuru() && $user->id === $examSession->user_id;
    }

    public function update(User $user, ExamSession $examSession): bool
    {
        return $user->isGuru() && $user->id === $examSession->user_id;
    }

    public function delete(User $user, ExamSession $examSession): bool
    {
        return $user->isGuru() && $user->id === $examSession->user_id;
    }
}
