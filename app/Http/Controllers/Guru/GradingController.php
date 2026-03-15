<?php

declare(strict_types=1);

namespace App\Http\Controllers\Guru;

use App\Enums\ExamAttemptStatus;
use App\Enums\QuestionType;
use App\Http\Controllers\Controller;
use App\Models\ExamAttempt;
use App\Models\ExamSession;
use App\Models\StudentAnswer;
use App\Services\Exam\GradingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        $examSessions = ExamSession::where('user_id', $request->user()->id)
            ->whereIn('status', ['completed', 'active'])
            ->with(['subject', 'classrooms'])
            ->withCount([
                'attempts as total_attempts' => fn ($q) => $q->where('status', '!=', ExamAttemptStatus::InProgress),
                'attempts as graded_attempts' => fn ($q) => $q->where('is_fully_graded', true),
            ])
            ->latest()
            ->paginate(15)
            ->withQueryString();

        // Add ungraded essay count for each session
        $examSessions->getCollection()->transform(function (ExamSession $session) {
            $attemptIds = $session->attempts()
                ->where('status', '!=', ExamAttemptStatus::InProgress)
                ->pluck('id');

            $session->ungraded_essays = StudentAnswer::whereIn('exam_attempt_id', $attemptIds)
                ->whereHas('question', fn ($q) => $q->whereIn('type', [
                    QuestionType::Esai,
                    QuestionType::IsianSingkat,
                ]))
                ->whereNull('score')
                ->count();

            return $session;
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

        $examSession->load(['subject', 'classrooms']);

        $attempts = $examSession->attempts()
            ->whereIn('status', [ExamAttemptStatus::Submitted, ExamAttemptStatus::Graded])
            ->with('user')
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
                ];
            });

        $statistics = $this->gradingService->getExamStatistics($examSession);
        $progress = $this->gradingService->getGradingProgress($examSession);

        return Inertia::render('Guru/Penilaian/Show', [
            'examSession' => $examSession,
            'attempts' => $attempts,
            'statistics' => $statistics,
            'progress' => $progress,
        ]);
    }

    /**
     * Manual grading interface for a specific attempt.
     */
    public function manualGrading(Request $request, ExamSession $examSession, ExamAttempt $attempt): Response
    {
        $this->authorize('view', $examSession);

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
     * Export exam results as CSV.
     */
    public function exportResults(ExamSession $examSession)
    {
        $this->authorize('view', $examSession);

        $csv = $this->gradingService->generateExportCsv($examSession);
        $filename = 'hasil-ujian-' . str_replace(' ', '-', strtolower($examSession->name)) . '.csv';

        return response()->streamDownload(function () use ($csv) {
            echo $csv;
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
