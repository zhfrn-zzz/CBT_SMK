<?php

declare(strict_types=1);

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Http\Requests\Guru\StoreDiscussionThreadRequest;
use App\Models\DiscussionReply;
use App\Models\DiscussionThread;
use App\Models\TeachingAssignment;
use App\Services\LMS\DiscussionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DiscussionController extends Controller
{
    public function __construct(private readonly DiscussionService $service) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        $assignments = TeachingAssignment::with(['classroom', 'subject'])
            ->where('user_id', $user->id)
            ->get();

        $subjectId = $request->integer('subject_id') ?: null;
        $classroomId = $request->integer('classroom_id') ?: null;
        $search = $request->string('search')->value() ?: null;

        $threads = null;
        if ($subjectId && $classroomId) {
            $threads = $this->service->getThreads($subjectId, $classroomId, $search)
                ->withQueryString();
        }

        return Inertia::render('Guru/Forum/Index', [
            'threads' => $threads,
            'teachingAssignments' => $assignments,
            'filters' => ['subject_id' => $subjectId, 'classroom_id' => $classroomId, 'search' => $search],
        ]);
    }

    public function show(DiscussionThread $thread, Request $request): Response
    {
        $this->authorize('view', $thread);

        $thread->load(['user', 'subject', 'classroom']);

        $replies = $thread->replies()
            ->with('user')
            ->orderBy('created_at')
            ->paginate(20);

        return Inertia::render('Guru/Forum/Show', [
            'thread' => $thread,
            'replies' => $replies,
        ]);
    }

    public function store(StoreDiscussionThreadRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;

        $thread = $this->service->createThread($data);

        return redirect()->route('guru.forum.show', $thread)
            ->with('success', 'Thread berhasil dibuat.');
    }

    public function destroy(DiscussionThread $thread): RedirectResponse
    {
        $this->authorize('delete', $thread);

        $this->service->deleteThread($thread);

        return redirect()->route('guru.forum.index')
            ->with('success', 'Thread berhasil dihapus.');
    }

    public function reply(DiscussionThread $thread, Request $request): RedirectResponse
    {
        $this->authorize('reply', $thread);

        $request->validate(['content' => ['required', 'string']]);

        $this->service->createReply($thread, [
            'user_id' => $request->user()->id,
            'content' => $request->input('content'),
        ]);

        return back()->with('success', 'Balasan berhasil dikirim.');
    }

    public function deleteReply(DiscussionReply $reply, Request $request): RedirectResponse
    {
        $this->authorize('delete', $reply->thread);

        $this->service->deleteReply($reply);

        return back()->with('success', 'Balasan berhasil dihapus.');
    }

    public function togglePin(DiscussionThread $thread): RedirectResponse
    {
        $this->authorize('pin', $thread);

        $this->service->togglePin($thread);

        return back()->with('success', $thread->is_pinned ? 'Thread di-unpin.' : 'Thread di-pin.');
    }

    public function toggleLock(DiscussionThread $thread): RedirectResponse
    {
        $this->authorize('lock', $thread);

        $this->service->toggleLock($thread);

        return back()->with('success', $thread->is_locked ? 'Thread dibuka.' : 'Thread dikunci.');
    }
}
