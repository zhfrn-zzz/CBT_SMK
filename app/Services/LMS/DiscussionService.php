<?php

declare(strict_types=1);

namespace App\Services\LMS;

use App\Events\DiscussionReplyCreated;
use App\Models\DiscussionReply;
use App\Models\DiscussionThread;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class DiscussionService
{
    public function getThreads(int $subjectId, int $classroomId, ?string $search = null): LengthAwarePaginator
    {
        $query = DiscussionThread::with(['user', 'latestReply.user'])
            ->where('subject_id', $subjectId)
            ->where('classroom_id', $classroomId)
            ->pinnedFirst();

        if ($search) {
            $query->where('title', 'like', "%{$search}%");
        }

        return $query->paginate(20);
    }

    public function createThread(array $data): DiscussionThread
    {
        return DiscussionThread::create($data);
    }

    public function deleteThread(DiscussionThread $thread): void
    {
        $thread->delete();
    }

    public function togglePin(DiscussionThread $thread): void
    {
        $thread->update(['is_pinned' => ! $thread->is_pinned]);
    }

    public function toggleLock(DiscussionThread $thread): void
    {
        $thread->update(['is_locked' => ! $thread->is_locked]);
    }

    public function createReply(DiscussionThread $thread, array $data): DiscussionReply
    {
        if ($thread->is_locked) {
            throw new \RuntimeException('Thread sudah dikunci, tidak bisa membalas.');
        }

        $reply = $thread->replies()->create($data);

        event(new DiscussionReplyCreated($reply, $thread));

        return $reply;
    }

    public function deleteReply(DiscussionReply $reply): void
    {
        $reply->delete();
    }
}
