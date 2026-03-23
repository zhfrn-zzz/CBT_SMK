<?php

declare(strict_types=1);

namespace App\Http\Controllers\Guru;

use App\Enums\ExamAttemptStatus;
use App\Enums\ExamStatus;
use App\Enums\QuestionType;
use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Attendance;
use App\Models\ExamAttempt;
use App\Models\ExamSession;
use App\Models\Material;
use App\Models\StudentAnswer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $guru = $request->user();
        $guruId = $guru->id;

        /** @var array<string, mixed> $data */
        $data = Cache::remember("dashboard:guru:{$guruId}", 300, function () use ($guru, $guruId): array {
            $classCount = $guru->teachingClassrooms()->distinct('classrooms.id')->count('classrooms.id');

            $upcomingExams = ExamSession::where('user_id', $guruId)
                ->whereIn('status', [ExamStatus::Scheduled, ExamStatus::Active])
                ->where('starts_at', '>=', now())
                ->count();

            $guruExamIds = ExamSession::where('user_id', $guruId)->pluck('id');
            $attemptIds = ExamAttempt::whereIn('exam_session_id', $guruExamIds)
                ->whereIn('status', [ExamAttemptStatus::Submitted, ExamAttemptStatus::Graded])
                ->pluck('id');

            $ungradedEssays = StudentAnswer::whereIn('exam_attempt_id', $attemptIds)
                ->whereHas('question', fn ($q) => $q->whereIn('type', [
                    QuestionType::Esai,
                    QuestionType::IsianSingkat,
                ]))
                ->whereNull('score')
                ->count();

            $recentExams = ExamSession::where('user_id', $guruId)
                ->with('subject')
                ->withCount([
                    'attempts as total_attempts' => fn ($q) => $q->where('status', '!=', ExamAttemptStatus::InProgress),
                ])
                ->latest()
                ->take(5)
                ->get()
                ->map(fn (ExamSession $s): array => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'subject' => $s->subject->name,
                    'status' => $s->status->value,
                    'status_label' => $s->status->label(),
                    'total_attempts' => $s->total_attempts,
                    'starts_at' => $s->starts_at->toISOString(),
                ]);

            // Phase 3: LMS stats (keep existing)
            $guruAssignmentIds = Assignment::where('user_id', $guruId)->pluck('id');
            $pendingSubmissions = AssignmentSubmission::whereIn('assignment_id', $guruAssignmentIds)
                ->whereNull('graded_at')
                ->count();

            $todayAttendanceSessions = Attendance::where('user_id', $guruId)
                ->where('meeting_date', today())
                ->where('is_open', true)
                ->with(['classroom', 'subject'])
                ->get();

            $recentMaterials = Material::where('user_id', $guruId)
                ->with(['subject', 'classroom'])
                ->latest()
                ->take(3)
                ->get();

            // F4.2: Add with('user') to prevent N+1 on serialization
            $recentAnnouncements = Announcement::where('user_id', $guruId)
                ->with('user:id,name')
                ->latest()
                ->take(3)
                ->get();

            // Today section
            $guruClassroomIds = $guru->teachingClassrooms()->pluck('classrooms.id');

            $todayAnnouncements = Announcement::published()
                ->where(fn ($q) => $q->whereIn('classroom_id', $guruClassroomIds)->orWhereNull('classroom_id'))
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

            $activeExams = ExamSession::where('user_id', $guruId)
                ->where('status', ExamStatus::Active)
                ->with('subject')
                ->withCount(['attempts as in_progress_count' => fn ($q) => $q->where('status', ExamAttemptStatus::InProgress)])
                ->get()
                ->map(fn (ExamSession $s): array => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'subject' => $s->subject->name,
                    'in_progress_count' => $s->in_progress_count,
                ]);

            $pendingGrading = [];
            if ($attemptIds->isNotEmpty()) {
                $pendingGrading = StudentAnswer::query()
                    ->join('questions', 'student_answers.question_id', '=', 'questions.id')
                    ->join('exam_attempts', 'student_answers.exam_attempt_id', '=', 'exam_attempts.id')
                    ->join('exam_sessions', 'exam_attempts.exam_session_id', '=', 'exam_sessions.id')
                    ->join('subjects', 'exam_sessions.subject_id', '=', 'subjects.id')
                    ->whereIn('student_answers.exam_attempt_id', $attemptIds)
                    ->whereIn('questions.type', [QuestionType::Esai->value, QuestionType::IsianSingkat->value])
                    ->whereNull('student_answers.score')
                    ->selectRaw('subjects.name as subject_name, count(*) as total')
                    ->groupBy('subjects.id', 'subjects.name')
                    ->get()
                    ->map(fn ($row): array => [
                        'subject' => $row->subject_name,
                        'count' => (int) $row->total,
                    ])
                    ->toArray();
            }

            $todayAttendance = Attendance::where('user_id', $guruId)
                ->where('meeting_date', today())
                ->with(['classroom', 'subject'])
                ->get()
                ->map(fn (Attendance $a): array => [
                    'id' => $a->id,
                    'classroom' => $a->classroom->name ?? '-',
                    'subject' => $a->subject->name ?? '-',
                    'meeting_number' => $a->meeting_number,
                    'is_open' => $a->is_open,
                ]);

            return [
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
                'todaySection' => [
                    'announcements' => $todayAnnouncements,
                    'active_exams' => $activeExams,
                    'pending_grading' => $pendingGrading,
                    'today_attendance' => $todayAttendance,
                ],
            ];
        });

        return Inertia::render('Guru/Dashboard', $data);
    }
}
