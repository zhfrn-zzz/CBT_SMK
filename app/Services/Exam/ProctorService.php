<?php

declare(strict_types=1);

namespace App\Services\Exam;

use App\Enums\ExamAttemptStatus;
use App\Events\ExamForceSubmitted;
use App\Events\ExamTimeExtended;
use App\Events\StudentSubmittedExam;
use App\Models\ExamActivityLog;
use App\Models\ExamAttempt;
use App\Models\ExamSession;
use App\Models\StudentAnswer;
use App\Models\User;

class ProctorService
{
    public function __construct(
        private readonly ExamAttemptService $attemptService,
    ) {}

    /**
     * Get proctor dashboard data: all students and their attempt status.
     */
    public function getDashboardData(ExamSession $examSession): array
    {
        $examSession->load([
            'subject',
            'classrooms.students',
            'attempts' => fn ($q) => $q->with(['user', 'activityLogs'])
                ->withCount(['answers as answered_count' => fn ($q) => $q->whereNotNull('answer')]),
        ]);

        // Get total questions for this exam
        $totalQuestions = $examSession->pool_count
            ?? $examSession->questionBank->questions()->count();

        // Build student list from assigned classrooms
        $allStudents = $examSession->classrooms
            ->flatMap(fn ($classroom) => $classroom->students)
            ->unique('id');

        $attemptsByUser = $examSession->attempts->keyBy('user_id');

        $students = $allStudents->map(function (User $student) use ($attemptsByUser, $totalQuestions) {
            $attempt = $attemptsByUser->get($student->id);

            $violationCount = 0;
            if ($attempt) {
                $violationCount = $attempt->activityLogs->count();
            }

            return [
                'id' => $student->id,
                'name' => $student->name,
                'username' => $student->username,
                'attempt_id' => $attempt?->id,
                'status' => $attempt?->status->value ?? 'not_started',
                'status_label' => $attempt?->status->label() ?? 'Belum Mulai',
                'started_at' => $attempt?->started_at?->toISOString(),
                'submitted_at' => $attempt?->submitted_at?->toISOString(),
                'is_force_submitted' => $attempt?->is_force_submitted ?? false,
                'answered_count' => $attempt?->answered_count ?? 0,
                'total_questions' => $totalQuestions,
                'remaining_seconds' => $attempt?->calculateRemainingSeconds() ?? 0,
                'violation_count' => $violationCount,
                'score' => $attempt?->score,
                'ip_address' => $attempt?->ip_address,
            ];
        })->sortBy('name')->values()->toArray();

        // Build questions list for invalidation UI
        $questions = $examSession->questionBank->questions()
            ->select('id', 'order', 'content')
            ->orderBy('order')
            ->get()
            ->map(fn ($q) => [
                'id' => $q->id,
                'order' => $q->order,
                'content' => strip_tags($q->content),
            ])
            ->toArray();

        return [
            'exam_session' => [
                'id' => $examSession->id,
                'name' => $examSession->name,
                'subject' => $examSession->subject->name,
                'status' => $examSession->status->value,
                'duration_minutes' => $examSession->duration_minutes,
                'starts_at' => $examSession->starts_at->toISOString(),
                'ends_at' => $examSession->ends_at->toISOString(),
                'token' => $examSession->token,
                'total_questions' => $totalQuestions,
                'max_tab_switches' => $examSession->max_tab_switches,
            ],
            'students' => $students,
            'questions' => $questions,
            'summary' => [
                'total' => count($students),
                'not_started' => collect($students)->where('status', 'not_started')->count(),
                'in_progress' => collect($students)->where('status', 'in_progress')->count(),
                'submitted' => collect($students)->where('status', 'submitted')->count(),
                'graded' => collect($students)->where('status', 'graded')->count(),
            ],
        ];
    }

    /**
     * Extend time for a specific student.
     */
    public function extendTime(ExamAttempt $attempt, int $additionalMinutes, User $proctorUser): void
    {
        // Extend by moving started_at back (effectively adding time)
        $attempt->update([
            'started_at' => $attempt->started_at->subMinutes($additionalMinutes),
        ]);

        $newRemaining = $attempt->calculateRemainingSeconds();

        // Log the override
        ExamActivityLog::create([
            'exam_attempt_id' => $attempt->id,
            'event_type' => 'proctor_extend_time',
            'description' => "Guru {$proctorUser->name} menambah waktu {$additionalMinutes} menit",
            'created_at' => now(),
        ]);

        // Broadcast to student
        event(new ExamTimeExtended(
            $attempt->exam_session_id,
            $attempt->user_id,
            $additionalMinutes,
            $newRemaining,
        ));
    }

    /**
     * Terminate/force-submit a student's exam.
     */
    public function terminate(ExamAttempt $attempt, User $proctorUser, string $reason = 'Diterminasi oleh pengawas'): void
    {
        if ($attempt->status !== ExamAttemptStatus::InProgress) {
            return;
        }

        // Log the override before submit
        ExamActivityLog::create([
            'exam_attempt_id' => $attempt->id,
            'event_type' => 'proctor_terminate',
            'description' => "Guru {$proctorUser->name}: {$reason}",
            'created_at' => now(),
        ]);

        $this->attemptService->submitExam($attempt, true);
        $attempt->refresh();

        // Broadcast to proctor channel
        event(new StudentSubmittedExam($attempt));

        // Broadcast to student
        event(new ExamForceSubmitted(
            $attempt->exam_session_id,
            $attempt->user_id,
            $reason,
        ));
    }

    /**
     * Invalidate a question — all students get full points for it.
     */
    public function invalidateQuestion(ExamSession $examSession, int $questionId, User $proctorUser): int
    {
        $question = $examSession->questionBank->questions()->findOrFail($questionId);

        // Update all student_answers for this question in this exam to full points
        $affected = StudentAnswer::whereHas('attempt', fn ($q) => $q->where('exam_session_id', $examSession->id))
            ->where('question_id', $questionId)
            ->update([
                'is_correct' => true,
                'score' => $question->points,
                'feedback' => 'Soal dibatalkan oleh pengawas — nilai penuh otomatis.',
            ]);

        // Log for all active attempts
        $activeAttempts = $examSession->attempts()
            ->where('status', ExamAttemptStatus::InProgress)
            ->get();

        foreach ($activeAttempts as $attempt) {
            ExamActivityLog::create([
                'exam_attempt_id' => $attempt->id,
                'event_type' => 'proctor_invalidate_question',
                'description' => "Guru {$proctorUser->name} membatalkan soal #{$question->order}",
                'created_at' => now(),
            ]);
        }

        return $affected;
    }
}
