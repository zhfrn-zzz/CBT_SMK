<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\ExamStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\ExamSession;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        /** @var array<string, mixed> $data */
        $data = Cache::remember('dashboard:admin', 300, function (): array {
            $totalUsers = User::count();
            $totalGuru = User::where('role', UserRole::Guru)->count();
            $totalSiswa = User::where('role', UserRole::Siswa)->count();

            $activeExams = ExamSession::where('status', ExamStatus::Active)->count();

            $today = now()->toDateString();
            $examsToday = ExamSession::whereDate('starts_at', $today)
                ->orWhere(function ($q) use ($today): void {
                    $q->whereDate('starts_at', '<=', $today)
                        ->whereDate('ends_at', '>=', $today);
                })
                ->count();

            $recentExams = ExamSession::with(['subject', 'user:id,name'])
                ->latest()
                ->take(5)
                ->get()
                ->map(fn (ExamSession $s): array => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'subject' => $s->subject->name,
                    'guru' => $s->user->name,
                    'status' => $s->status->value,
                    'status_label' => $s->status->label(),
                    'starts_at' => $s->starts_at->toISOString(),
                ]);

            $todayAnnouncements = Announcement::published()
                ->pinnedFirst()
                ->with('user:id,name')
                ->take(3)
                ->get()
                ->map(fn (Announcement $a): array => [
                    'id' => $a->id,
                    'title' => $a->title,
                    'content' => $a->content,
                    'is_pinned' => $a->is_pinned,
                    'published_at' => $a->published_at->toISOString(),
                    'user' => $a->user ? ['id' => $a->user->id, 'name' => $a->user->name] : null,
                ]);

            $recentAuditLogs = AuditLog::with('user:id,name,role')
                ->latest('created_at')
                ->take(10)
                ->get()
                ->map(fn (AuditLog $log): array => [
                    'id' => $log->id,
                    'action' => $log->action,
                    'description' => $log->description,
                    'user' => $log->user ? [
                        'id' => $log->user->id,
                        'name' => $log->user->name,
                        'role' => $log->user->role->value,
                    ] : null,
                    'created_at' => $log->created_at->toISOString(),
                ]);

            $activeExamNames = ExamSession::where('status', ExamStatus::Active)
                ->pluck('name')
                ->toArray();

            return [
                'stats' => [
                    'total_users' => $totalUsers,
                    'total_guru' => $totalGuru,
                    'total_siswa' => $totalSiswa,
                    'active_exams' => $activeExams,
                    'exams_today' => $examsToday,
                ],
                'recentExams' => $recentExams,
                'todaySection' => [
                    'announcements' => $todayAnnouncements,
                    'recentAuditLogs' => $recentAuditLogs,
                    'activeExamAlert' => [
                        'count' => count($activeExamNames),
                        'names' => $activeExamNames,
                    ],
                ],
            ];
        });

        return Inertia::render('Admin/Dashboard', $data);
    }
}
