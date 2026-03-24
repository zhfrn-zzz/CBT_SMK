<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\QuestionBank;
use App\Models\User;

class QuestionBankPolicy
{
    public function view(User $user, QuestionBank $questionBank): bool
    {
        return $user->isGuru() && $user->id === $questionBank->user_id;
    }

    public function update(User $user, QuestionBank $questionBank): bool
    {
        return $user->isGuru() && $user->id === $questionBank->user_id;
    }

    public function delete(User $user, QuestionBank $questionBank): bool
    {
        return $user->isGuru() && $user->id === $questionBank->user_id;
    }
}
