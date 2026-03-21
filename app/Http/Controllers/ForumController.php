<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ForumCategory;
use App\Models\ForumReply;
use App\Models\ForumThread;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ForumController extends Controller
{
    public function index(Request $request): Response
    {
        $query = ForumThread::query()
            ->with(['user:id,name,role,photo_path', 'category:id,name,slug,color'])
            ->withCount('replies');

        if ($request->filled('category')) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $request->input('category')));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%");
            });
        }

        $threads = $query->pinnedFirst()->paginate(20)->withQueryString();

        $categories = ForumCategory::where('is_active', true)
            ->orderBy('order')
            ->get(['id', 'name', 'slug', 'color']);

        return Inertia::render('Forum/Index', [
            'threads' => $threads,
            'categories' => $categories,
            'filters' => $request->only(['category', 'search']),
        ]);
    }

    public function create(): Response
    {
        $categories = ForumCategory::where('is_active', true)
            ->orderBy('order')
            ->get(['id', 'name', 'slug', 'color']);

        return Inertia::render('Forum/Create', [
            'categories' => $categories,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'forum_category_id' => ['nullable', 'exists:forum_categories,id'],
        ]);

        $thread = ForumThread::create([
            ...$validated,
            'user_id' => $request->user()->id,
        ]);

        return redirect()->route('forum.show', $thread)
            ->with('success', 'Thread berhasil dibuat.');
    }

    public function show(ForumThread $thread, Request $request): Response
    {
        $thread->increment('view_count');

        $thread->load(['user:id,name,role,photo_path', 'category:id,name,slug,color']);

        $replies = $thread->replies()
            ->with('user:id,name,role,photo_path')
            ->orderBy('created_at')
            ->paginate(20);

        return Inertia::render('Forum/Show', [
            'thread' => $thread,
            'replies' => $replies,
            'can' => [
                'reply' => ! $thread->is_locked,
                'pin' => $request->user()->isAdmin() || $request->user()->isGuru(),
                'lock' => $request->user()->isAdmin() || $request->user()->isGuru(),
                'delete' => $request->user()->isAdmin() || $request->user()->isGuru() || $thread->user_id === $request->user()->id,
            ],
        ]);
    }

    public function reply(ForumThread $thread, Request $request): RedirectResponse
    {
        if ($thread->is_locked) {
            return back()->with('error', 'Thread ini sudah dikunci.');
        }

        $request->validate([
            'content' => ['required', 'string'],
        ]);

        ForumReply::create([
            'forum_thread_id' => $thread->id,
            'user_id' => $request->user()->id,
            'content' => $request->input('content'),
        ]);

        return back()->with('success', 'Balasan berhasil dikirim.');
    }

    public function destroy(ForumThread $thread, Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->isAdmin() && ! $user->isGuru() && $thread->user_id !== $user->id) {
            abort(403, 'Anda tidak memiliki akses untuk menghapus thread ini.');
        }

        $thread->delete();

        return redirect()->route('forum.index')
            ->with('success', 'Thread berhasil dihapus.');
    }

    public function destroyReply(ForumReply $reply, Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->isAdmin() && ! $user->isGuru() && $reply->user_id !== $user->id) {
            abort(403, 'Anda tidak memiliki akses untuk menghapus balasan ini.');
        }

        $reply->delete();

        return back()->with('success', 'Balasan berhasil dihapus.');
    }

    public function togglePin(ForumThread $thread): RedirectResponse
    {
        $thread->update(['is_pinned' => ! $thread->is_pinned]);

        $message = $thread->is_pinned ? 'Thread di-pin.' : 'Thread di-unpin.';

        return back()->with('success', $message);
    }

    public function toggleLock(ForumThread $thread): RedirectResponse
    {
        $thread->update(['is_locked' => ! $thread->is_locked]);

        $message = $thread->is_locked ? 'Thread dikunci.' : 'Thread dibuka.';

        return back()->with('success', $message);
    }
}
