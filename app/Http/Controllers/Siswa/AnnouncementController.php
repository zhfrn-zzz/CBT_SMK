<?php

declare(strict_types=1);

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Services\LMS\AnnouncementService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AnnouncementController extends Controller
{
    public function __construct(private readonly AnnouncementService $service) {}

    public function index(Request $request): Response
    {
        $student = $request->user();
        $announcements = $this->service->getForStudent($student)->withQueryString();

        return Inertia::render('Siswa/Pengumuman/Index', [
            'announcements' => $announcements,
        ]);
    }

    public function show(Announcement $announcement, Request $request): Response
    {
        $student = $request->user();

        // Only visible if broadcast or student in classroom
        if ($announcement->classroom_id !== null) {
            $inClassroom = $student->classrooms()
                ->where('classrooms.id', $announcement->classroom_id)
                ->exists();
            if (! $inClassroom) {
                abort(403);
            }
        }

        // Must be published
        if ($announcement->published_at->isFuture()) {
            abort(404);
        }

        $announcement->load(['user', 'classroom', 'subject']);

        return Inertia::render('Siswa/Pengumuman/Show', [
            'announcement' => $announcement,
        ]);
    }
}
