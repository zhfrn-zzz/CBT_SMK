<?php

declare(strict_types=1);

namespace App\Services\Exam;

use App\Enums\ExamAttemptStatus;
use App\Enums\ExamStatus;
use App\Enums\QuestionType;
use App\Models\ExamAttempt;
use App\Models\ExamSession;
use App\Models\StudentAnswer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class ExamAttemptService
{
    public function __construct(
        private readonly ExamRandomizerService $randomizer,
    ) {}

    /**
     * Validate token dan return exam session.
     */
    public function verifyToken(string $token): ?ExamSession
    {
        return ExamSession::where('token', $token)
            ->with(['subject', 'questionBank'])
            ->first();
    }

    /**
     * Start exam: create attempt + generate question set.
     */
    public function startExam(ExamSession $examSession, User $student, string $ipAddress): ExamAttempt
    {
        return DB::transaction(function () use ($examSession, $student, $ipAddress) {
            $attempt = ExamAttempt::create([
                'exam_session_id' => $examSession->id,
                'user_id' => $student->id,
                'started_at' => now(),
                'ip_address' => $ipAddress,
                'status' => ExamAttemptStatus::InProgress,
            ]);

            // Generate randomized question set
            $questionSet = $this->randomizer->generateQuestionSet($examSession);

            foreach ($questionSet as $item) {
                $attempt->attemptQuestions()->create($item);
            }

            // Pre-create empty student_answers for all questions
            foreach ($questionSet as $item) {
                StudentAnswer::create([
                    'exam_attempt_id' => $attempt->id,
                    'question_id' => $item['question_id'],
                ]);
            }

            return $attempt;
        });
    }

    /**
     * Build payload for exam interface.
     */
    public function buildExamPayload(ExamAttempt $attempt): array
    {
        $attempt->load([
            'examSession.subject',
            'attemptQuestions.question.options',
            'answers',
        ]);

        $session = $attempt->examSession;
        $now = now();

        // Calculate remaining seconds from server
        $elapsed = (int) $attempt->started_at->diffInSeconds($now);
        $total = $session->duration_minutes * 60;
        $remaining = max(0, $total - $elapsed);

        // Also check against exam end time
        $endRemaining = max(0, (int) $now->diffInSeconds($session->ends_at));
        $remaining = min($remaining, $endRemaining);

        // Build questions array
        $questions = $attempt->attemptQuestions->map(function ($aq) {
            $question = $aq->question;

            $options = null;
            if ($question->options->isNotEmpty()) {
                $labels = $aq->option_order;

                if ($labels) {
                    // Reorder options according to randomized order
                    $optionsByLabel = $question->options->keyBy('label');
                    $options = collect($labels)->map(fn (string $label) => [
                        'id' => $optionsByLabel[$label]->id,
                        'label' => $label,
                        'content' => $optionsByLabel[$label]->content,
                        'media_url' => $optionsByLabel[$label]->media_path
                            ? '/storage/' . $optionsByLabel[$label]->media_path
                            : null,
                    ])->values()->toArray();
                } else {
                    $options = $question->options->map(fn ($opt) => [
                        'id' => $opt->id,
                        'label' => $opt->label,
                        'content' => $opt->content,
                        'media_url' => $opt->media_path
                            ? '/storage/' . $opt->media_path
                            : null,
                    ])->toArray();
                }
            }

            return [
                'id' => $question->id,
                'order' => $aq->order,
                'content' => $question->content,
                'type' => $question->type->value,
                'media_url' => $question->media_url,
                'points' => (float) $question->points,
                'options' => $options,
            ];
        })->sortBy('order')->values()->toArray();

        // Build saved answers (from DB)
        $savedAnswers = [];
        $flaggedQuestions = [];
        foreach ($attempt->answers as $answer) {
            if ($answer->answer !== null) {
                $savedAnswers[(string) $answer->question_id] = $answer->answer;
            }
            if ($answer->is_flagged) {
                $flaggedQuestions[] = $answer->question_id;
            }
        }

        // Check Redis for more recent answers
        $redisKey = "exam:{$session->id}:student:{$attempt->user_id}:answers";
        $redisAnswers = Redis::get($redisKey);
        if ($redisAnswers) {
            $redisData = json_decode($redisAnswers, true);
            if (is_array($redisData)) {
                $savedAnswers = array_replace($savedAnswers, $redisData);
            }
        }

        $redisFlagKey = "exam:{$session->id}:student:{$attempt->user_id}:flags";
        $redisFlags = Redis::get($redisFlagKey);
        if ($redisFlags) {
            $flagData = json_decode($redisFlags, true);
            if (is_array($flagData)) {
                $flaggedQuestions = $flagData;
            }
        }

        return [
            'attempt_id' => $attempt->id,
            'exam' => [
                'id' => $session->id,
                'name' => $session->name,
                'subject' => $session->subject->name,
                'duration_minutes' => $session->duration_minutes,
                'total_questions' => count($questions),
                'max_tab_switches' => $session->max_tab_switches,
            ],
            'questions' => $questions,
            'saved_answers' => (object) $savedAnswers,
            'flagged_questions' => $flaggedQuestions,
            'started_at' => $attempt->started_at->timestamp,
            'server_time' => $now->timestamp,
            'remaining_seconds' => $remaining,
        ];
    }

    /**
     * Save answers to Redis (fast).
     */
    public function saveAnswersToRedis(ExamAttempt $attempt, array $answers, array $flags = []): array
    {
        $session = $attempt->examSession;
        $redisKey = "exam:{$session->id}:student:{$attempt->user_id}:answers";
        $redisFlagKey = "exam:{$session->id}:student:{$attempt->user_id}:flags";
        $lastSaveKey = "exam:{$session->id}:student:{$attempt->user_id}:last_save";

        $ttl = max(3600, $session->ends_at->diffInSeconds(now()) + 86400);

        Redis::setex($redisKey, (int) $ttl, json_encode($answers));
        Redis::setex($lastSaveKey, (int) $ttl, (string) now()->timestamp);

        if (! empty($flags)) {
            Redis::setex($redisFlagKey, (int) $ttl, json_encode($flags));
        }

        // Return server time for client sync
        $now = now();
        $elapsed = (int) $attempt->started_at->diffInSeconds($now);
        $total = $session->duration_minutes * 60;
        $remaining = max(0, $total - $elapsed);
        $endRemaining = max(0, (int) $now->diffInSeconds($session->ends_at));
        $remaining = min($remaining, $endRemaining);

        return [
            'saved' => true,
            'server_time' => $now->timestamp,
            'remaining_seconds' => $remaining,
        ];
    }

    /**
     * Submit exam: persist all answers to MySQL, auto-grade, clear Redis.
     */
    public function submitExam(ExamAttempt $attempt, bool $isForceSubmit = false): void
    {
        if ($attempt->status !== ExamAttemptStatus::InProgress) {
            return;
        }

        $session = $attempt->examSession;

        // Get answers from Redis first
        $redisKey = "exam:{$session->id}:student:{$attempt->user_id}:answers";
        $redisAnswers = Redis::get($redisKey);

        DB::transaction(function () use ($attempt, $isForceSubmit, $redisAnswers) {
            // Persist Redis answers to MySQL
            if ($redisAnswers) {
                $answers = json_decode($redisAnswers, true);
                if (is_array($answers)) {
                    foreach ($answers as $questionId => $answer) {
                        StudentAnswer::updateOrCreate(
                            [
                                'exam_attempt_id' => $attempt->id,
                                'question_id' => (int) $questionId,
                            ],
                            [
                                'answer' => $answer,
                                'answered_at' => now(),
                            ]
                        );
                    }
                }
            }

            // Persist flags
            $redisFlagKey = "exam:{$attempt->examSession->id}:student:{$attempt->user_id}:flags";
            $redisFlags = Redis::get($redisFlagKey);
            if ($redisFlags) {
                $flags = json_decode($redisFlags, true);
                if (is_array($flags)) {
                    StudentAnswer::where('exam_attempt_id', $attempt->id)
                        ->whereIn('question_id', $flags)
                        ->update(['is_flagged' => true]);
                }
            }

            // Auto-grade PG and Benar/Salah
            $this->autoGrade($attempt);

            // Update attempt status
            $attempt->update([
                'status' => ExamAttemptStatus::Submitted,
                'submitted_at' => now(),
                'is_force_submitted' => $isForceSubmit,
            ]);
        });

        // Clear Redis keys
        $this->clearRedisKeys($attempt);
    }

    /**
     * Auto-grade: PG (pilihan_ganda) dan Benar/Salah.
     */
    private function autoGrade(ExamAttempt $attempt): void
    {
        $answers = $attempt->answers()->with('question.options')->get();
        $allAutoGraded = true;

        foreach ($answers as $studentAnswer) {
            $question = $studentAnswer->question;

            if (in_array($question->type, [QuestionType::PilihanGanda, QuestionType::BenarSalah])) {
                $correctOption = $question->options->firstWhere('is_correct', true);

                if ($correctOption && $studentAnswer->answer !== null) {
                    $isCorrect = $studentAnswer->answer === $correctOption->label;
                    $studentAnswer->update([
                        'is_correct' => $isCorrect,
                        'score' => $isCorrect ? $question->points : 0,
                    ]);
                } else {
                    $studentAnswer->update([
                        'is_correct' => false,
                        'score' => 0,
                    ]);
                }
            } else {
                // Esai, isian singkat, etc. — belum auto-grade
                $allAutoGraded = false;
            }
        }

        // Calculate total score if all auto-graded
        if ($allAutoGraded) {
            $totalScore = $attempt->answers()->sum('score');
            $maxScore = $attempt->attemptQuestions()
                ->join('questions', 'questions.id', '=', 'exam_attempt_questions.question_id')
                ->sum('questions.points');

            $percentage = $maxScore > 0 ? ($totalScore / $maxScore) * 100 : 0;

            $attempt->update([
                'score' => round($percentage, 2),
                'is_fully_graded' => true,
            ]);
        }
    }

    /**
     * Clear Redis keys setelah submit.
     */
    private function clearRedisKeys(ExamAttempt $attempt): void
    {
        $session = $attempt->examSession;
        $prefix = "exam:{$session->id}:student:{$attempt->user_id}";

        Redis::del("{$prefix}:answers");
        Redis::del("{$prefix}:flags");
        Redis::del("{$prefix}:last_save");
    }
}
