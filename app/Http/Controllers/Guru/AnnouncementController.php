<?php

declare(strict_types=1);

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Http\Requests\Guru\StoreAnnouncementRequest;
use App\Http\Requests\Guru\UpdateAnnouncementRequest;
use App\Models\Announcement;
use App\Models\Classroom;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Notifications\PengumumanBaruNotification;
use App\Services\LMS\AnnouncementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

class AnnouncementController extends Controller
{
    public function __construct(private readonly AnnouncementService $service) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $classroomId = $request->integer('classroom_id') ?: null;

        $announcements = Announcement::with(['classroom', 'subject'])
            ->where('user_id', $user->id)
            ->pinnedFirst()
            ->paginate(15)
            ->withQueryString();

        $assignments = TeachingAssignment::with(['classroom', 'subject'])
            ->where('user_id', $user->id)
            ->get();

        return Inertia::render('Guru/Pengumuman/Index', [
            'announcements' => $announcements,
            'teachingAssignments' => $assignments,
        ]);
    }

    public function create(Request $request): Response
    {
        $assignments = TeachingAssignment::with(['classroom', 'subject'])
            ->where('user_id', $request->user()->id)
            ->get();

        return Inertia::render('Guru/Pengumuman/Create', [
            'teachingAssignments' => $assignments,
        ]);
    }

    public function store(StoreAnnouncementRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;
        $data['published_at'] = $data['published_at'] ?? now();
        $data['is_pinned'] = $data['is_pinned'] ?? false;

        $announcement = $this->service->create($data);

        // Notify students in the target classroom (or all siswa if no classroom)
        if ($announcement->classroom_id) {
            $students = Classroom::find($announcement->classroom_id)?->students ?? collect();
        } else {
            $students = User::where('role', 'siswa')->where('is_active', true)->get();
        }

        if ($students->isNotEmpty()) {
            Notification::send($students, new PengumumanBaruNotification($announcement));
        }

        return redirect()->route('guru.pengumuman.index')
            ->with('success', 'Pengumuman berhasil dibuat.');
    }

    public function edit(Announcement $announcement): Response
    {
        $this->authorize('update', $announcement);

        $assignments = TeachingAssignment::with(['classroom', 'subject'])
            ->where('user_id', auth()->id())
            ->get();

        return Inertia::render('Guru/Pengumuman/Edit', [
            'announcement' => $announcement->load(['classroom', 'subject']),
            'teachingAssignments' => $assignments,
        ]);
    }

    public function update(UpdateAnnouncementRequest $request, Announcement $announcement): RedirectResponse
    {
        $this->authorize('update', $announcement);

        $this->service->update($announcement, $request->validated());

        return redirect()->route('guru.pengumuman.index')
            ->with('success', 'Pengumuman berhasil diperbarui.');
    }

    public function destroy(Announcement $announcement): RedirectResponse
    {
        $this->authorize('delete', $announcement);

        $this->service->delete($announcement);

        return redirect()->route('guru.pengumuman.index')
            ->with('success', 'Pengumuman berhasil dihapus.');
    }

    public function togglePin(Announcement $announcement): RedirectResponse
    {
        $this->authorize('update', $announcement);

        $this->service->togglePin($announcement);

        return back()->with('success', 'Status pin pengumuman berhasil diubah.');
    }
}
