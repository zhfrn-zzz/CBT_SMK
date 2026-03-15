<?php

declare(strict_types=1);

namespace App\Http\Controllers\Siswa;

use App\Enums\ExamAttemptStatus;
use App\Enums\ExamStatus;
use App\Events\AnswerProgressUpdated;
use App\Events\StudentStartedExam;
use App\Events\StudentSubmittedExam;
use App\Events\TabSwitchDetected;
use App\Http\Controllers\Controller;
use App\Models\ExamActivityLog;
use App\Models\ExamAttempt;
use App\Models\ExamSession;
use App\Services\Exam\ExamAttemptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExamController extends Controller
{
    public function __construct(
        private readonly ExamAttemptService $attemptService,
    ) {}

    /**
     * List ujian untuk siswa: upcoming, in-progress, completed.
     */
    public function index(Request $request): Response
    {
        $student = $request->user();

        // Get classrooms siswa
        $classroomIds = $student->classrooms()->pluck('classrooms.id');

        // Get exam sessions for student's classrooms
        $examSessions = ExamSession::whereHas('classrooms', fn ($q) => $q->whereIn('classrooms.id', $classroomIds))
            ->whereIn('status', [ExamStatus::Scheduled, ExamStatus::Active, ExamStatus::Completed])
            ->with(['subject', 'attempts' => fn ($q) => $q->where('user_id', $student->id)])
            ->orderBy('starts_at')
            ->get()
            ->map(function (ExamSession $session) {
                $attempt = $session->attempts->first();

                return [
                    'id' => $session->id,
                    'name' => $session->name,
                    'subject' => $session->subject->name,
                    'duration_minutes' => $session->duration_minutes,
                    'starts_at' => $session->starts_at->toISOString(),
                    'ends_at' => $session->ends_at->toISOString(),
                    'status' => $session->status->value,
                    'status_label' => $session->status->label(),
                    'attempt_status' => $attempt?->status->value,
                    'attempt_status_label' => $attempt?->status->label(),
                    'score' => $attempt?->score,
                    'is_published' => $session->is_published,
                ];
            });

        return Inertia::render('Siswa/Ujian/Index', [
            'examSessions' => $examSessions,
        ]);
    }

    /**
     * Token verification page.
     */
    public function showVerifyToken(ExamSession $ujian): Response
    {
        return Inertia::render('Siswa/Ujian/VerifyToken', [
            'examSession' => [
                'id' => $ujian->id,
                'name' => $ujian->name,
                'subject' => $ujian->subject->name,
                'duration_minutes' => $ujian->duration_minutes,
            ],
        ]);
    }

    /**
     * Verify token & redirect to start.
     */
    public function verifyToken(Request $request, ExamSession $ujian): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
        ]);

        if (strtoupper($request->string('token')->value()) !== $ujian->token) {
            return back()->withErrors(['token' => 'Token tidak valid.']);
        }

        if (! $ujian->isActive()) {
            return back()->withErrors(['token' => 'Ujian belum dimulai atau sudah selesai.']);
        }

        if (! $ujian->isWithinTimeWindow()) {
            return back()->withErrors(['token' => 'Ujian di luar waktu yang ditentukan.']);
        }

        // Check if student already has an attempt
        $existingAttempt = ExamAttempt::where('exam_session_id', $ujian->id)
            ->where('user_id', $request->user()->id)
            ->first();

        if ($existingAttempt) {
            if ($existingAttempt->status === ExamAttemptStatus::InProgress) {
                return redirect()->route('siswa.ujian.exam', $ujian);
            }

            return back()->withErrors(['token' => 'Anda sudah mengerjakan ujian ini.']);
        }

        // Redirect to start confirmation
        return redirect()->route('siswa.ujian.start', $ujian);
    }

    /**
     * Start exam: create attempt and redirect to exam interface.
     */
    public function start(Request $request, ExamSession $ujian): Response|RedirectResponse
    {
        $student = $request->user();

        // Check existing attempt
        $existingAttempt = ExamAttempt::where('exam_session_id', $ujian->id)
            ->where('user_id', $student->id)
            ->first();

        if ($existingAttempt) {
            if ($existingAttempt->status === ExamAttemptStatus::InProgress) {
                // Resume
                $payload = $this->attemptService->buildExamPayload($existingAttempt);

                return Inertia::render('Siswa/Ujian/ExamInterface', $payload);
            }

            return redirect()->route('siswa.ujian.index')
                ->with('error', 'Anda sudah mengerjakan ujian ini.');
        }

        // Validate exam is active and within time window
        if (! $ujian->isActive() || ! $ujian->isWithinTimeWindow()) {
            return redirect()->route('siswa.ujian.index')
                ->with('error', 'Ujian tidak tersedia.');
        }

        // Create attempt
        $attempt = $this->attemptService->startExam(
            $ujian,
            $student,
            $request->ip() ?? '0.0.0.0',
        );

        event(new StudentStartedExam($attempt));

        $payload = $this->attemptService->buildExamPayload($attempt);

        return Inertia::render('Siswa/Ujian/ExamInterface', $payload);
    }

    /**
     * Resume an in-progress exam.
     */
    public function exam(Request $request, ExamSession $ujian): Response|RedirectResponse
    {
        $attempt = ExamAttempt::where('exam_session_id', $ujian->id)
            ->where('user_id', $request->user()->id)
            ->where('status', ExamAttemptStatus::InProgress)
            ->first();

        if (! $attempt) {
            return redirect()->route('siswa.ujian.index')
                ->with('error', 'Tidak ada ujian yang sedang berlangsung.');
        }

        // Check if expired
        if ($attempt->isExpired()) {
            $this->attemptService->submitExam($attempt, true);

            return redirect()->route('siswa.ujian.index')
                ->with('info', 'Waktu ujian telah habis. Jawaban Anda telah dikumpulkan.');
        }

        $payload = $this->attemptService->buildExamPayload($attempt);

        return Inertia::render('Siswa/Ujian/ExamInterface', $payload);
    }

    /**
     * Save answers (auto-save endpoint).
     */
    public function saveAnswers(Request $request, ExamSession $ujian): JsonResponse
    {
        $request->validate([
            'answers' => ['required', 'array'],
            'flags' => ['sometimes', 'array'],
        ]);

        $attempt = ExamAttempt::where('exam_session_id', $ujian->id)
            ->where('user_id', $request->user()->id)
            ->where('status', ExamAttemptStatus::InProgress)
            ->first();

        if (! $attempt) {
            return response()->json(['error' => 'Tidak ada ujian aktif.'], 404);
        }

        if ($attempt->isExpired()) {
            $this->attemptService->submitExam($attempt, true);

            return response()->json(['error' => 'Waktu habis.', 'expired' => true], 410);
        }

        $result = $this->attemptService->saveAnswersToRedis(
            $attempt,
            $request->input('answers'),
            $request->input('flags', []),
        );

        $answeredCount = count(array_filter($request->input('answers'), fn ($v) => $v !== null && $v !== ''));
        $totalQuestions = $attempt->attemptQuestions()->count();

        event(new AnswerProgressUpdated(
            $ujian->id,
            $request->user()->id,
            $answeredCount,
            $totalQuestions,
        ));

        return response()->json($result);
    }

    /**
     * Submit exam.
     */
    public function submit(Request $request, ExamSession $ujian): RedirectResponse
    {
        $attempt = ExamAttempt::where('exam_session_id', $ujian->id)
            ->where('user_id', $request->user()->id)
            ->where('status', ExamAttemptStatus::InProgress)
            ->first();

        if (! $attempt) {
            return redirect()->route('siswa.ujian.index')
                ->with('error', 'Tidak ada ujian aktif.');
        }

        $this->attemptService->submitExam($attempt);

        $attempt->refresh();
        event(new StudentSubmittedExam($attempt));

        return redirect()->route('siswa.ujian.index')
            ->with('success', 'Jawaban berhasil dikumpulkan.');
    }

    /**
     * Log anti-cheat activity.
     */
    public function logActivity(Request $request): JsonResponse
    {
        $request->validate([
            'attempt_id' => ['required', 'exists:exam_attempts,id'],
            'event_type' => ['required', 'string'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $attempt = ExamAttempt::where('id', $request->input('attempt_id'))
            ->where('user_id', $request->user()->id)
            ->where('status', ExamAttemptStatus::InProgress)
            ->first();

        if (! $attempt) {
            return response()->json(['error' => 'Invalid attempt.'], 404);
        }

        ExamActivityLog::create([
            'exam_attempt_id' => $attempt->id,
            'event_type' => $request->input('event_type'),
            'description' => $request->input('description'),
            'created_at' => now(),
        ]);

        $attempt->load('examSession');
        $totalViolations = ExamActivityLog::where('exam_attempt_id', $attempt->id)->count();

        event(new TabSwitchDetected(
            $attempt->examSession->id,
            $request->user()->id,
            $request->user()->name,
            $request->input('event_type'),
            $totalViolations,
        ));

        return response()->json(['logged' => true]);
    }
}
