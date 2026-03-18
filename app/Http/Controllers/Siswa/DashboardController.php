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

        // Phase 3: LMS stats
        $submittedAssignmentIds = AssignmentSubmission::where('user_id', $student->id)->pluck('assignment_id');
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
                ->where('user_id', $student->id)
                ->exists();
            $todayAttendance = [
                'has_session' => true,
                'is_checked_in' => $isCheckedIn,
                'attendance_id' => $todaySession->id,
            ];
        }

        return Inertia::render('Siswa/Dashboard', [
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
        ]);
    }
}
