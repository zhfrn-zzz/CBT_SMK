<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Material;
use App\Models\User;

class MaterialPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Material $material): bool
    {
        if ($user->isGuru()) {
            return $user->id === $material->user_id;
        }

        if ($user->isSiswa()) {
            return $user->classrooms()->where('classrooms.id', $material->classroom_id)->exists()
                && $material->is_published;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->isGuru();
    }

    public function update(User $user, Material $material): bool
    {
        return $user->isGuru() && $user->id === $material->user_id;
    }

    public function delete(User $user, Material $material): bool
    {
        return $user->isGuru() && $user->id === $material->user_id;
    }
}
