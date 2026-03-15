<?php

declare(strict_types=1);

namespace App\Http\Controllers\Siswa;

use App\Enums\ExamAttemptStatus;
use App\Enums\ExamStatus;
use App\Http\Controllers\Controller;
use App\Models\ExamAttempt;
use App\Models\ExamSession;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $student = $request->user();
        $classroomIds = $student->classrooms()->pluck('classrooms.id');

        // Upcoming exams for student's classrooms
        $upcomingExams = ExamSession::whereHas('classrooms', fn ($q) => $q->whereIn('classrooms.id', $classroomIds))
            ->whereIn('status', [ExamStatus::Scheduled, ExamStatus::Active])
            ->where('starts_at', '>=', now())
            ->count();

        // Completed exams
        $completedExams = ExamAttempt::where('user_id', $student->id)
            ->whereIn('status', [ExamAttemptStatus::Submitted, ExamAttemptStatus::Graded])
            ->count();

        // Latest score
        $latestAttempt = ExamAttempt::where('user_id', $student->id)
            ->whereIn('status', [ExamAttemptStatus::Submitted, ExamAttemptStatus::Graded])
            ->whereHas('examSession', fn ($q) => $q->where('is_results_published', true))
            ->latest('submitted_at')
            ->first();

        $latestScore = $latestAttempt?->score !== null ? (float) $latestAttempt->score : null;

        // Recent results (published only)
        $recentResults = ExamAttempt::where('user_id', $student->id)
            ->whereIn('status', [ExamAttemptStatus::Submitted, ExamAttemptStatus::Graded])
            ->whereHas('examSession', fn ($q) => $q->where('is_results_published', true))
            ->with(['examSession.subject'])
            ->latest('submitted_at')
            ->take(5)
            ->get()
            ->map(function (ExamAttempt $attempt) {
                $session = $attempt->examSession;
                $kkm = (float) ($session->kkm ?? 0);
                $passStatus = null;
                if ($kkm > 0 && $attempt->score !== null) {
                    $passStatus = (float) $attempt->score >= $kkm ? 'lulus' : 'remedial';
                }

                return [
                    'id' => $attempt->id,
                    'exam_name' => $session->name,
                    'subject' => $session->subject->name,
                    'score' => $attempt->score !== null ? (float) $attempt->score : null,
                    'pass_status' => $passStatus,
                    'submitted_at' => $attempt->submitted_at?->toISOString(),
                ];
            });

        return Inertia::render('Siswa/Dashboard', [
            'stats' => [
                'upcoming_exams' => $upcomingExams,
                'completed_exams' => $completedExams,
                'latest_score' => $latestScore,
            ],
            'recentResults' => $recentResults,
        ]);
    }
}
