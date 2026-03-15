<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\ExamStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\ExamSession;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $totalUsers = User::count();
        $totalGuru = User::where('role', UserRole::Guru)->count();
        $totalSiswa = User::where('role', UserRole::Siswa)->count();

        $activeExams = ExamSession::where('status', ExamStatus::Active)->count();

        $today = now()->toDateString();
        $examsToday = ExamSession::whereDate('starts_at', $today)
            ->orWhere(function ($q) use ($today) {
                $q->whereDate('starts_at', '<=', $today)
                    ->whereDate('ends_at', '>=', $today);
            })
            ->count();

        $recentExams = ExamSession::with(['subject', 'user:id,name'])
            ->latest()
            ->take(5)
            ->get()
            ->map(fn (ExamSession $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'subject' => $s->subject->name,
                'guru' => $s->user->name,
                'status' => $s->status->value,
                'status_label' => $s->status->label(),
                'starts_at' => $s->starts_at->toISOString(),
            ]);

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'total_users' => $totalUsers,
                'total_guru' => $totalGuru,
                'total_siswa' => $totalSiswa,
                'active_exams' => $activeExams,
                'exams_today' => $examsToday,
            ],
            'recentExams' => $recentExams,
        ]);
    }
}
