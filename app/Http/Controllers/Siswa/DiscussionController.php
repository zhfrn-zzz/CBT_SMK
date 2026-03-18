<?php

declare(strict_types=1);

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Controller;
use App\Http\Requests\Siswa\StoreDiscussionThreadRequest;
use App\Models\DiscussionReply;
use App\Models\DiscussionThread;
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
        $student = $request->user();
        $classrooms = $student->classrooms()->with('subjects')->get();

        $subjectId = $request->integer('subject_id') ?: null;
        $classroomId = $request->integer('classroom_id') ?: null;
        $search = $request->string('search')->value() ?: null;

        $threads = null;
        if ($subjectId && $classroomId) {
            $inClassroom = $student->classrooms()->where('classrooms.id', $classroomId)->exists();
            if ($inClassroom) {
                $threads = $this->service->getThreads($subjectId, $classroomId, $search)->withQueryString();
            }
        }

        return Inertia::render('Siswa/Forum/Index', [
            'threads' => $threads,
            'classrooms' => $classrooms,
            'filters' => ['subject_id' => $subjectId, 'classroom_id' => $classroomId, 'search' => $search],
        ]);
    }

    public function show(DiscussionThread $thread, Request $request): Response
    {
        $student = $request->user();
        $inClassroom = $student->classrooms()->where('classrooms.id', $thread->classroom_id)->exists();
        if (! $inClassroom) {
            abort(403);
        }

        $thread->load(['user', 'subject', 'classroom']);

        $replies = $thread->replies()
            ->with('user')
            ->orderBy('created_at')
            ->paginate(20);

        return Inertia::render('Siswa/Forum/Show', [
            'thread' => $thread,
            'replies' => $replies,
        ]);
    }

    public function store(StoreDiscussionThreadRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;

        $thread = $this->service->createThread($data);

        return redirect()->route('siswa.forum.show', $thread)
            ->with('success', 'Thread berhasil dibuat.');
    }

    public function destroy(DiscussionThread $thread, Request $request): RedirectResponse
    {
        $student = $request->user();
        if ($thread->user_id !== $student->id) {
            abort(403);
        }

        $this->service->deleteThread($thread);

        return redirect()->route('siswa.forum.index')
            ->with('success', 'Thread berhasil dihapus.');
    }

    public function reply(DiscussionThread $thread, Request $request): RedirectResponse
    {
        $student = $request->user();
        $inClassroom = $student->classrooms()->where('classrooms.id', $thread->classroom_id)->exists();
        if (! $inClassroom) {
            abort(403);
        }

        if ($thread->is_locked) {
            return back()->withErrors(['reply' => 'Thread sudah dikunci.']);
        }

        $request->validate(['content' => ['required', 'string']]);

        $this->service->createReply($thread, [
            'user_id' => $student->id,
            'content' => $request->input('content'),
        ]);

        return back()->with('success', 'Balasan berhasil dikirim.');
    }

    public function deleteReply(DiscussionReply $reply, Request $request): RedirectResponse
    {
        $student = $request->user();
        if ($reply->user_id !== $student->id) {
            abort(403);
        }

        $this->service->deleteReply($reply);

        return back()->with('success', 'Balasan berhasil dihapus.');
    }
}
