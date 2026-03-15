<?php

declare(strict_types=1);

namespace App\Http\Controllers\Siswa;

use App\Enums\ExamAttemptStatus;
use App\Http\Controllers\Controller;
use App\Models\ExamAttempt;
use App\Models\ExamSession;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExamResultController extends Controller
{
    /**
     * List published exam results for the student.
     */
    public function index(Request $request): Response
    {
        $student = $request->user();
        $classroomIds = $student->classrooms()->pluck('classrooms.id');

        $results = ExamAttempt::where('user_id', $student->id)
            ->whereIn('status', [ExamAttemptStatus::Submitted, ExamAttemptStatus::Graded])
            ->whereHas('examSession', function ($q) use ($classroomIds) {
                $q->where('is_results_published', true)
                    ->whereHas('classrooms', fn ($cq) => $cq->whereIn('classrooms.id', $classroomIds));
            })
            ->with(['examSession.subject'])
            ->latest('submitted_at')
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
                    'exam_session_id' => $session->id,
                    'exam_name' => $session->name,
                    'subject' => $session->subject->name,
                    'score' => $attempt->score !== null ? (float) $attempt->score : null,
                    'kkm' => $kkm > 0 ? $kkm : null,
                    'pass_status' => $passStatus,
                    'is_fully_graded' => $attempt->is_fully_graded,
                    'submitted_at' => $attempt->submitted_at?->toISOString(),
                ];
            });

        return Inertia::render('Siswa/Nilai/Index', [
            'results' => $results,
        ]);
    }

    /**
     * Show detailed result for a specific attempt.
     */
    public function show(Request $request, ExamAttempt $attempt): Response
    {
        $student = $request->user();

        // Verify this attempt belongs to the student
        if ($attempt->user_id !== $student->id) {
            abort(403);
        }

        // Verify results are published
        $examSession = $attempt->examSession;
        if (! $examSession->is_results_published) {
            abort(403, 'Hasil ujian belum dipublikasikan.');
        }

        $attempt->load([
            'examSession.subject',
            'answers.question.options',
        ]);

        $kkm = (float) ($examSession->kkm ?? 0);
        $passStatus = null;
        if ($kkm > 0 && $attempt->score !== null) {
            $passStatus = (float) $attempt->score >= $kkm ? 'lulus' : 'remedial';
        }

        $answers = $attempt->answers->map(function ($answer) {
            $question = $answer->question;

            return [
                'id' => $answer->id,
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
            ];
        });

        return Inertia::render('Siswa/Nilai/Show', [
            'attempt' => [
                'id' => $attempt->id,
                'score' => $attempt->score !== null ? (float) $attempt->score : null,
                'is_fully_graded' => $attempt->is_fully_graded,
                'submitted_at' => $attempt->submitted_at?->toISOString(),
                'pass_status' => $passStatus,
            ],
            'examSession' => [
                'id' => $examSession->id,
                'name' => $examSession->name,
                'subject' => $examSession->subject->name,
                'kkm' => $kkm > 0 ? $kkm : null,
            ],
            'answers' => $answers,
        ]);
    }
}
