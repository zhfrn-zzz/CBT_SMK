<?php

declare(strict_types=1);

namespace App\Services\LMS;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class AnnouncementService
{
    public function getForClassroom(?int $classroomId, ?int $subjectId = null): LengthAwarePaginator
    {
        $query = Announcement::with(['user', 'classroom', 'subject'])
            ->pinnedFirst();

        if ($classroomId) {
            $query->where(function ($q) use ($classroomId) {
                $q->where('classroom_id', $classroomId)
                    ->orWhereNull('classroom_id');
            });
        }

        if ($subjectId) {
            $query->where(function ($q) use ($subjectId) {
                $q->where('subject_id', $subjectId)
                    ->orWhereNull('subject_id');
            });
        }

        return $query->paginate(10);
    }

    public function getForStudent(User $student): LengthAwarePaginator
    {
        return Announcement::with(['user', 'classroom', 'subject'])
            ->published()
            ->forStudent($student)
            ->pinnedFirst()
            ->paginate(10);
    }

    public function create(array $data): Announcement
    {
        return Announcement::create($data);
    }

    public function update(Announcement $announcement, array $data): Announcement
    {
        $announcement->update($data);

        return $announcement->fresh();
    }

    public function delete(Announcement $announcement): void
    {
        $announcement->delete();
    }

    public function togglePin(Announcement $announcement): void
    {
        $announcement->update(['is_pinned' => ! $announcement->is_pinned]);
    }
}
