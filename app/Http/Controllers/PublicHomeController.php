<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\ExamSession;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicHomeController extends Controller
{
    public function index(Request $request): Response
    {
        $announcements = Announcement::query()
            ->where('is_public', true)
            ->published()
            ->pinnedFirst()
            ->limit(5)
            ->get(['id', 'title', 'content', 'published_at', 'is_pinned']);

        $examSchedules = ExamSession::query()
            ->where('is_published', true)
            ->where('starts_at', '>', now())
            ->orderBy('starts_at')
            ->limit(5)
            ->with('subject:id,name')
            ->get(['id', 'name', 'subject_id', 'starts_at', 'ends_at', 'duration_minutes']);

        return Inertia::render('Welcome', [
            'announcements' => $announcements,
            'examSchedules' => $examSchedules,
            'school' => [
                'name' => setting('school_name', config('school.name')),
                'address' => setting('school_address', config('school.address')),
                'logo_path' => setting('logo_path', config('school.logo_path')),
                'tagline' => setting('school_tagline', config('school.tagline')),
                'footer_text' => setting('footer_text', ''),
                'show_powered_by' => setting('show_powered_by', true),
                'app_name' => setting('app_name', config('app.name')),
            ],
        ]);
    }
}
