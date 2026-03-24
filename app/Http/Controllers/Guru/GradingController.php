<?php

declare(strict_types=1);

namespace App\Http\Controllers\Guru;

use App\Enums\ExamAttemptStatus;
use App\Enums\QuestionType;
use App\Http\Controllers\Controller;
use App\Models\ExamActivityLog;
use App\Models\ExamAttempt;
use App\Models\ExamSession;
use App\Models\StudentAnswer;
use App\Notifications\NilaiDipublikasiNotification;
use App\Services\Exam\GradingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

class GradingController extends Controller
{
    public function __construct(
        private readonly GradingService $gradingService,
    ) {}

    /**
     * List exam sessions for grading.
     */
    public function index(Request $request): Response
    {
        $guruId = $request->user()->id;
        $page = (int) $request->input('page', 1);
        $cacheKey = "grading:guru:{$guruId}:page:{$page}";

        // F5.3: Cache grading index with 120s TTL
        $examSessions = Cache::remember($cacheKey, 120, function () use ($guruId) {
            $sessions = ExamSession::where('user_id', $guruId)
                ->whereIn('status', ['completed', 'active'])
                ->with(['subject', 'classrooms'])
                ->withCount([
                    'attempts as total_attempts' => fn ($q) => $q->where('status', '!=', ExamAttemptStatus::InProgress),
                    'attempts as graded_attempts' => fn ($q) => $q->where('is_fully_graded', true),
                ])
                ->latest()
                ->paginate(15)
                ->withQueryString();

            // F3.3: Batch ungraded essay count — single JOIN query replaces N+1 loop
            $sessionIds = $sessions->getCollection()->pluck('id');

            $ungradedCounts = StudentAnswer::query()
                ->join('exam_attempts', 'student_answers.exam_attempt_id', '=', 'exam_attempts.id')
                ->join('questions', 'student_answers.question_id', '=', 'questions.id')
                ->whereIn('exam_attempts.exam_session_id', $sessionIds)
                ->where('exam_attempts.status', '!=', ExamAttemptStatus::InProgress->value)
                ->whereIn('questions.type', [QuestionType::Esai->value, QuestionType::IsianSingkat->value])
                ->whereNull('student_answers.score')
                ->selectRaw('exam_attempts.exam_session_id, COUNT(*) as ungraded_count')
                ->groupBy('exam_attempts.exam_session_id')
                ->pluck('ungraded_count', 'exam_session_id');

            $sessions->getCollection()->transform(function (ExamSession $session) use ($ungradedCounts) {
                $session->ungraded_essays = (int) ($ungradedCounts[$session->id] ?? 0);

                return $session;
            });

            return $sessions;
        });

        return Inertia::render('Guru/Penilaian/Index', [
            'examSessions' => $examSessions,
        ]);
    }

    /**
     * Show exam results — all students with scores.
     */
    public function show(Request $request, ExamSession $examSession): Response
    {
        $this->authorize('view', $examSession);

        $examSession->load(['subject', 'classrooms', 'remedialExamSessions']);

        $attempts = $examSession->attempts()
            ->whereIn('status', [ExamAttemptStatus::Submitted, ExamAttemptStatus::Graded])
            ->with('user')
            ->withCount('activityLogs as violation_count')
            ->orderBy('id')
            ->get()
            ->map(function (ExamAttempt $attempt) use ($examSession) {
                $kkm = (float) ($examSession->kkm ?? 0);
                $passStatus = null;
                if ($kkm > 0 && $attempt->score !== null) {
                    $passStatus = (float) $attempt->score >= $kkm ? 'lulus' : 'remedial';
                }

                return [
                    'id' => $attempt->id,
                    'user' => [
                        'id' => $attempt->user->id,
                        'name' => $attempt->user->name,
                        'username' => $attempt->user->username,
                    ],
                    'started_at' => $attempt->started_at->toISOString(),
                    'submitted_at' => $attempt->submitted_at?->toISOString(),
                    'score' => $attempt->score !== null ? (float) $attempt->score : null,
                    'is_fully_graded' => $attempt->is_fully_graded,
                    'is_force_submitted' => $attempt->is_force_submitted,
                    'status' => $attempt->status->value,
                    'pass_status' => $passStatus,
                    'violation_count' => $attempt->violation_count ?? 0,
                ];
            });

        $statistics = $this->gradingService->getExamStatistics($examSession);
        $progress = $this->gradingService->getGradingProgress($examSession);

        // Get remedial info
        $remedialExams = $examSession->remedialExamSessions->map(fn (ExamSession $r) => [
            'id' => $r->id,
            'name' => $r->name,
            'status' => $r->status->value,
            'remedial_policy' => $r->remedial_policy,
        ]);

        return Inertia::render('Guru/Penilaian/Show', [
            'examSession' => $examSession,
            'attempts' => $attempts,
            'statistics' => $statistics,
            'progress' => $progress,
            'remedialExams' => $remedialExams,
            'isRemedial' => $examSession->isRemedial(),
        ]);
    }

    /**
     * Manual grading interface for a specific attempt.
     */
    public function manualGrading(Request $request, ExamSession $examSession, ExamAttempt $attempt): Response
    {
        $this->authorize('view', $examSession);

        if ($attempt->exam_session_id !== $examSession->id) {
            abort(404);
        }

        $attempt->load('user');

        $answers = $attempt->answers()
            ->with(['question.options'])
            ->get()
            ->map(function (StudentAnswer $answer) {
                $question = $answer->question;

                return [
                    'id' => $answer->id,
                    'question_id' => $answer->question_id,
                    'question' => [
                        'id' => $question->id,
                        'type' => $question->type->value,
                        'type_label' => $question->type->label(),
                        'content' => $question->content,
                        'points' => (float) $question->points,
                        'explanation' => $question->explanation,
                        'media_url' => $question->media_url,
                        'options' => $question->options->map(fn ($opt) => [
                            'label' => $opt->label,
                            'content' => $opt->content,
                            'is_correct' => $opt->is_correct,
                        ])->toArray(),
                    ],
                    'answer' => $answer->answer,
                    'score' => $answer->score !== null ? (float) $answer->score : null,
                    'is_correct' => $answer->is_correct,
                    'feedback' => $answer->feedback,
                    'needs_grading' => in_array($question->type, [QuestionType::Esai, QuestionType::IsianSingkat])
                        && $answer->score === null,
                ];
            });

        // Get other attempts for navigation
        $otherAttempts = $examSession->attempts()
            ->whereIn('status', [ExamAttemptStatus::Submitted, ExamAttemptStatus::Graded])
            ->with('user:id,name')
            ->get()
            ->map(fn (ExamAttempt $a) => [
                'id' => $a->id,
                'user_name' => $a->user->name,
                'is_fully_graded' => $a->is_fully_graded,
            ]);

        $gradedCount = $answers->filter(fn ($a) => $a['score'] !== null)->count();

        return Inertia::render('Guru/Penilaian/ManualGrading', [
            'examSession' => [
                'id' => $examSession->id,
                'name' => $examSession->name,
                'subject' => $examSession->subject->name,
            ],
            'attempt' => [
                'id' => $attempt->id,
                'user' => [
                    'id' => $attempt->user->id,
                    'name' => $attempt->user->name,
                    'username' => $attempt->user->username,
                ],
                'score' => $attempt->score !== null ? (float) $attempt->score : null,
                'is_fully_graded' => $attempt->is_fully_graded,
            ],
            'answers' => $answers,
            'otherAttempts' => $otherAttempts,
            'gradingProgress' => [
                'graded' => $gradedCount,
                'total' => $answers->count(),
            ],
        ]);
    }

    /**
     * Save grade for a single answer.
     */
    public function saveGrade(Request $request, ExamSession $examSession, StudentAnswer $answer): RedirectResponse
    {
        $this->authorize('view', $examSession);

        if ($answer->attempt->exam_session_id !== $examSession->id) {
            abort(404);
        }

        $request->validate([
            'score' => ['required', 'numeric', 'min:0'],
            'feedback' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->gradingService->saveGrade(
            $answer,
            (float) $request->input('score'),
            $request->input('feedback'),
        );

        return back()->with('success', 'Nilai berhasil disimpan.');
    }

    /**
     * Publish exam results.
     */
    public function publishResults(ExamSession $examSession): RedirectResponse
    {
        $this->authorize('update', $examSession);

        $examSession->update(['is_results_published' => true]);

        // Notify students with graded attempts
        $students = $examSession->attempts()
            ->where('is_fully_graded', true)
            ->with('user')
            ->get()
            ->map(fn ($attempt) => $attempt->user)
            ->filter();

        if ($students->isNotEmpty()) {
            Notification::send($students, new NilaiDipublikasiNotification($examSession));
        }

        return back()->with('success', 'Hasil ujian berhasil dipublikasikan.');
    }

    /**
     * Unpublish exam results.
     */
    public function unpublishResults(ExamSession $examSession): RedirectResponse
    {
        $this->authorize('update', $examSession);

        $examSession->update(['is_results_published' => false]);

        return back()->with('success', 'Publikasi hasil ujian dibatalkan.');
    }

    /**
     * Show activity logs for a specific attempt.
     */
    public function activityLog(ExamSession $examSession, ExamAttempt $attempt): Response
    {
        $this->authorize('view', $examSession);

        if ($attempt->exam_session_id !== $examSession->id) {
            abort(404);
        }

        $attempt->load('user');

        $logs = ExamActivityLog::where('exam_attempt_id', $attempt->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (ExamActivityLog $log) => [
                'id' => $log->id,
                'event_type' => $log->event_type->value,
                'event_label' => $log->event_type->label(),
                'description' => $log->description,
                'created_at' => $log->created_at->toISOString(),
            ]);

        $summary = [
            'total' => $logs->count(),
            'tab_switches' => $logs->where('event_type', 'tab_switch')->count(),
            'fullscreen_exits' => $logs->where('event_type', 'fullscreen_exit')->count(),
            'copy_attempts' => $logs->where('event_type', 'copy_attempt')->count(),
            'right_clicks' => $logs->where('event_type', 'right_click')->count(),
        ];

        return Inertia::render('Guru/Penilaian/ActivityLog', [
            'examSession' => [
                'id' => $examSession->id,
                'name' => $examSession->name,
                'subject' => $examSession->subject->name,
                'max_tab_switches' => $examSession->max_tab_switches,
            ],
            'attempt' => [
                'id' => $attempt->id,
                'user' => [
                    'id' => $attempt->user->id,
                    'name' => $attempt->user->name,
                    'username' => $attempt->user->username,
                ],
                'started_at' => $attempt->started_at->toISOString(),
                'submitted_at' => $attempt->submitted_at?->toISOString(),
                'is_force_submitted' => $attempt->is_force_submitted,
                'ip_address' => $attempt->ip_address,
                'user_agent' => $attempt->user_agent,
            ],
            'logs' => $logs,
            'summary' => $summary,
        ]);
    }

    /**
     * Export exam results as CSV.
     */
    public function exportResults(ExamSession $examSession)
    {
        $this->authorize('view', $examSession);

        $csv = $this->gradingService->generateExportCsv($examSession);
        $filename = 'hasil-ujian-'.str_replace(' ', '-', strtolower($examSession->name)).'.csv';

        return response()->streamDownload(function () use ($csv) {
            echo $csv;
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
