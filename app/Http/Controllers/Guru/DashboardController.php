<?php

declare(strict_types=1);

namespace App\Http\Controllers\Guru;

use App\Enums\ExamAttemptStatus;
use App\Enums\ExamStatus;
use App\Enums\QuestionType;
use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\AssignmentSubmission;
use App\Models\Attendance;
use App\Models\ExamSession;
use App\Models\Material;
use App\Models\StudentAnswer;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $guru = $request->user();

        $classCount = $guru->teachingClassrooms()->distinct('classrooms.id')->count('classrooms.id');

        $upcomingExams = ExamSession::where('user_id', $guru->id)
            ->whereIn('status', [ExamStatus::Scheduled, ExamStatus::Active])
            ->where('starts_at', '>=', now())
            ->count();

        // Ungraded essay answers across all guru's exams
        $guruExamIds = ExamSession::where('user_id', $guru->id)->pluck('id');
        $attemptIds = \App\Models\ExamAttempt::whereIn('exam_session_id', $guruExamIds)
            ->whereIn('status', [ExamAttemptStatus::Submitted, ExamAttemptStatus::Graded])
            ->pluck('id');

        $ungradedEssays = StudentAnswer::whereIn('exam_attempt_id', $attemptIds)
            ->whereHas('question', fn ($q) => $q->whereIn('type', [
                QuestionType::Esai,
                QuestionType::IsianSingkat,
            ]))
            ->whereNull('score')
            ->count();

        $recentExams = ExamSession::where('user_id', $guru->id)
            ->with('subject')
            ->withCount([
                'attempts as total_attempts' => fn ($q) => $q->where('status', '!=', ExamAttemptStatus::InProgress),
            ])
            ->latest()
            ->take(5)
            ->get()
            ->map(fn (ExamSession $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'subject' => $s->subject->name,
                'status' => $s->status->value,
                'status_label' => $s->status->label(),
                'total_attempts' => $s->total_attempts,
                'starts_at' => $s->starts_at->toISOString(),
            ]);

        // Phase 3: LMS stats
        $guruAssignmentIds = \App\Models\Assignment::where('user_id', $guru->id)->pluck('id');
        $pendingSubmissions = AssignmentSubmission::whereIn('assignment_id', $guruAssignmentIds)
            ->whereNull('graded_at')
            ->count();

        $todayAttendanceSessions = Attendance::where('user_id', $guru->id)
            ->where('meeting_date', today())
            ->where('is_open', true)
            ->with(['classroom', 'subject'])
            ->get();

        $recentMaterials = Material::where('user_id', $guru->id)
            ->with(['subject', 'classroom'])
            ->latest()
            ->take(3)
            ->get();

        $recentAnnouncements = Announcement::where('user_id', $guru->id)
            ->latest()
            ->take(3)
            ->get();

        return Inertia::render('Guru/Dashboard', [
            'stats' => [
                'class_count' => $classCount,
                'upcoming_exams' => $upcomingExams,
                'ungraded_essays' => $ungradedEssays,
                'pending_submissions' => $pendingSubmissions,
            ],
            'recentExams' => $recentExams,
            'lmsStats' => [
                'today_attendance_sessions' => $todayAttendanceSessions,
                'recent_materials' => $recentMaterials,
                'recent_announcements' => $recentAnnouncements,
            ],
        ]);
    }
}
