<?php

declare(strict_types=1);

namespace App\Http\Controllers\Siswa;

use App\Enums\ExamAttemptStatus;
use App\Enums\ExamStatus;
use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Attendance;
use App\Models\AttendanceRecord;
use App\Models\ExamAttempt;
use App\Models\ExamSession;
use App\Models\Material;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $student = $request->user();
        $studentId = $student->id;

        /** @var array<string, mixed> $data */
        $data = Cache::remember("dashboard:siswa:{$studentId}", 300, function () use ($student, $studentId): array {
            $classroomIds = $student->classrooms()->pluck('classrooms.id');

            // Upcoming exams for student's classrooms
            $upcomingExams = ExamSession::whereHas('classrooms', fn ($q) => $q->whereIn('classrooms.id', $classroomIds))
                ->whereIn('status', [ExamStatus::Scheduled, ExamStatus::Active])
                ->where('starts_at', '>=', now())
                ->count();

            // Completed exams
            $completedExams = ExamAttempt::where('user_id', $studentId)
                ->whereIn('status', [ExamAttemptStatus::Submitted, ExamAttemptStatus::Graded])
                ->count();

            // Latest score
            $latestAttempt = ExamAttempt::where('user_id', $studentId)
                ->whereIn('status', [ExamAttemptStatus::Submitted, ExamAttemptStatus::Graded])
                ->whereHas('examSession', fn ($q) => $q->where('is_results_published', true))
                ->latest('submitted_at')
                ->first();

            $latestScore = $latestAttempt?->score !== null ? (float) $latestAttempt->score : null;

            // Recent results (published only)
            $recentResults = ExamAttempt::where('user_id', $studentId)
                ->whereIn('status', [ExamAttemptStatus::Submitted, ExamAttemptStatus::Graded])
                ->whereHas('examSession', fn ($q) => $q->where('is_results_published', true))
                ->with(['examSession.subject'])
                ->latest('submitted_at')
                ->take(5)
                ->get()
                ->map(function (ExamAttempt $attempt): array {
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

            // Phase 3: LMS stats (keep existing)
            $submittedAssignmentIds = AssignmentSubmission::where('user_id', $studentId)->pluck('assignment_id');
            $upcomingAssignments = Assignment::whereIn('classroom_id', $classroomIds)
                ->where('is_published', true)
                ->where('deadline_at', '>', now())
                ->whereNotIn('id', $submittedAssignmentIds)
                ->with(['subject', 'classroom'])
                ->orderBy('deadline_at')
                ->take(5)
                ->get();

            $recentMaterials = Material::whereIn('classroom_id', $classroomIds)
                ->where('is_published', true)
                ->with(['subject', 'classroom'])
                ->latest()
                ->take(3)
                ->get();

            $recentAnnouncements = Announcement::published()
                ->forStudent($student)
                ->pinnedFirst()
                ->take(3)
                ->get();

            // Check attendance today
            $todayAttendance = null;
            $todaySession = Attendance::whereIn('classroom_id', $classroomIds)
                ->where('meeting_date', today())
                ->where('is_open', true)
                ->first();

            if ($todaySession) {
                $isCheckedIn = AttendanceRecord::where('attendance_id', $todaySession->id)
                    ->where('user_id', $studentId)
                    ->exists();
                $todayAttendance = [
                    'has_session' => true,
                    'is_checked_in' => $isCheckedIn,
                    'attendance_id' => $todaySession->id,
                ];
            }

            // Today section
            $todayAnnouncements = Announcement::published()
                ->forStudent($student)
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

            $endOfWeek = now()->endOfWeek();
            $upcomingExamsList = ExamSession::whereHas('classrooms', fn ($q) => $q->whereIn('classrooms.id', $classroomIds))
                ->whereIn('status', [ExamStatus::Scheduled, ExamStatus::Active])
                ->where('starts_at', '<=', $endOfWeek)
                ->where(function ($q): void {
                    $q->where('starts_at', '>=', now())
                        ->orWhere('status', ExamStatus::Active);
                })
                ->with('subject')
                ->orderBy('starts_at')
                ->take(5)
                ->get()
                ->map(fn (ExamSession $s): array => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'subject' => $s->subject->name,
                    'starts_at' => $s->starts_at->toISOString(),
                    'ends_at' => $s->ends_at?->toISOString(),
                    'status' => $s->status->value,
                    'status_label' => $s->status->label(),
                ]);

            $deadlineAssignments = Assignment::whereIn('classroom_id', $classroomIds)
                ->where('is_published', true)
                ->where('deadline_at', '>', now())
                ->where('deadline_at', '<=', now()->addDays(3))
                ->whereNotIn('id', $submittedAssignmentIds)
                ->with(['subject', 'classroom'])
                ->orderBy('deadline_at')
                ->take(3)
                ->get()
                ->map(fn (Assignment $a): array => [
                    'id' => $a->id,
                    'title' => $a->title,
                    'subject' => $a->subject->name,
                    'classroom' => $a->classroom->name,
                    'deadline_at' => $a->deadline_at->toISOString(),
                ]);

            $newMaterials = Material::whereIn('classroom_id', $classroomIds)
                ->where('is_published', true)
                ->where('created_at', '>=', now()->startOfWeek())
                ->with(['subject', 'classroom'])
                ->latest()
                ->take(3)
                ->get()
                ->map(fn (Material $m): array => [
                    'id' => $m->id,
                    'title' => $m->title,
                    'subject' => $m->subject->name,
                    'classroom' => $m->classroom->name,
                    'type' => $m->type->value,
                    'created_at' => $m->created_at->toISOString(),
                ]);

            return [
                'stats' => [
                    'upcoming_exams' => $upcomingExams,
                    'completed_exams' => $completedExams,
                    'latest_score' => $latestScore,
                ],
                'recentResults' => $recentResults,
                'lmsStats' => [
                    'upcoming_assignments' => $upcomingAssignments,
                    'recent_materials' => $recentMaterials,
                    'recent_announcements' => $recentAnnouncements,
                    'today_attendance' => $todayAttendance,
                ],
                'todaySection' => [
                    'announcements' => $todayAnnouncements,
                    'upcoming_exams' => $upcomingExamsList,
                    'deadline_assignments' => $deadlineAssignments,
                    'new_materials' => $newMaterials,
                ],
            ];
        });

        return Inertia::render('Siswa/Dashboard', $data);
    }
}
