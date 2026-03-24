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
    public function startExam(ExamSession $examSession, User $student, string $ipAddress, ?string $userAgent = null): ExamAttempt
    {
        return DB::transaction(function () use ($examSession, $student, $ipAddress, $userAgent) {
            $attempt = ExamAttempt::create([
                'exam_session_id' => $examSession->id,
                'user_id' => $student->id,
                'started_at' => now(),
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent ? substr($userAgent, 0, 500) : null,
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
            'attemptQuestions.question.matchingPairs',
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

                // For ordering type, shuffle the options for display
                if ($question->type === QuestionType::Ordering && ! $labels) {
                    shuffle($options);
                }
            }

            // Build matching pairs data
            $matchingPremises = null;
            $matchingResponses = null;
            if ($question->type === QuestionType::Menjodohkan && $question->matchingPairs->isNotEmpty()) {
                $matchingPremises = $question->matchingPairs->map(fn ($pair) => [
                    'id' => $pair->id,
                    'content' => $pair->premise,
                ])->values()->toArray();

                // Shuffle responses
                $responses = $question->matchingPairs->map(fn ($pair) => [
                    'id' => $pair->id,
                    'content' => $pair->response,
                ])->values()->toArray();
                shuffle($responses);
                $matchingResponses = $responses;
            }

            return [
                'id' => $question->id,
                'order' => $aq->order,
                'content' => $question->content,
                'type' => $question->type->value,
                'media_url' => $question->media_url,
                'points' => (float) $question->points,
                'options' => $options,
                'matching_premises' => $matchingPremises,
                'matching_responses' => $matchingResponses,
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
            'security_hardening' => (bool) config('exam.security_hardening', true),
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
        $session = $attempt->examSession;

        // Get answers from Redis first (before transaction to avoid holding lock)
        $redisKey = "exam:{$session->id}:student:{$attempt->user_id}:answers";
        $redisAnswers = Redis::get($redisKey);

        $redisFlagKey = "exam:{$session->id}:student:{$attempt->user_id}:flags";
        $redisFlags = Redis::get($redisFlagKey);

        $submitted = DB::transaction(function () use ($attempt, $isForceSubmit, $redisAnswers, $redisFlags) {
            // Pessimistic lock: atomic check-and-update to prevent double-submit
            $locked = ExamAttempt::lockForUpdate()
                ->where('id', $attempt->id)
                ->where('status', ExamAttemptStatus::InProgress)
                ->first();

            if (! $locked) {
                return false;
            }

            // Persist Redis answers to MySQL via batch upsert
            if ($redisAnswers) {
                $answers = json_decode($redisAnswers, true);
                if (is_array($answers)) {
                    $now = now()->toDateTimeString();
                    $values = [];

                    foreach ($answers as $questionId => $answer) {
                        $values[] = [
                            'exam_attempt_id' => $locked->id,
                            'question_id' => (int) $questionId,
                            'answer' => $answer,
                            'answered_at' => $now,
                            'updated_at' => $now,
                        ];
                    }

                    if (! empty($values)) {
                        StudentAnswer::upsert(
                            $values,
                            ['exam_attempt_id', 'question_id'],
                            ['answer', 'answered_at', 'updated_at']
                        );
                    }
                }
            }

            // Persist flags
            if ($redisFlags) {
                $flags = json_decode($redisFlags, true);
                if (is_array($flags)) {
                    StudentAnswer::where('exam_attempt_id', $locked->id)
                        ->whereIn('question_id', $flags)
                        ->update(['is_flagged' => true]);
                }
            }

            // Update attempt status
            $locked->update([
                'status' => ExamAttemptStatus::Submitted,
                'submitted_at' => now(),
                'is_force_submitted' => $isForceSubmit,
            ]);

            // Sync the original model
            $attempt->refresh();

            return true;
        });

        if (! $submitted) {
            return;
        }

        // Grade async via queue
        \App\Jobs\GradeExamJob::dispatch($attempt);

        // Clear Redis keys
        $this->clearRedisKeys($attempt);
    }

    /**
     * Auto-grade all auto-gradable question types.
     */
    public function autoGrade(ExamAttempt $attempt): void
    {
        $answers = $attempt->answers()->with(['question.options', 'question.matchingPairs', 'question.keywords'])->get();
        $allAutoGraded = true;

        foreach ($answers as $studentAnswer) {
            $question = $studentAnswer->question;

            match ($question->type) {
                QuestionType::PilihanGanda,
                QuestionType::BenarSalah => $this->gradeSingleChoice($studentAnswer),

                QuestionType::MultipleAnswer => $this->gradeMultipleAnswer($studentAnswer),

                QuestionType::IsianSingkat => $this->gradeIsianSingkat($studentAnswer),

                QuestionType::Menjodohkan => $this->gradeMenjodohkan($studentAnswer),

                QuestionType::Ordering => $this->gradeOrdering($studentAnswer),

                QuestionType::Esai => $allAutoGraded = false,
            };
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
     * Grade PG / Benar-Salah (single correct answer).
     */
    private function gradeSingleChoice(StudentAnswer $studentAnswer): void
    {
        $question = $studentAnswer->question;
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
    }

    /**
     * Grade Multiple Answer (multiple correct options).
     * Full score if all correct selected AND no incorrect selected.
     */
    private function gradeMultipleAnswer(StudentAnswer $studentAnswer): void
    {
        $question = $studentAnswer->question;

        if ($studentAnswer->answer === null) {
            $studentAnswer->update(['is_correct' => false, 'score' => 0]);

            return;
        }

        $selectedLabels = json_decode($studentAnswer->answer, true);
        if (! is_array($selectedLabels)) {
            $studentAnswer->update(['is_correct' => false, 'score' => 0]);

            return;
        }

        $correctLabels = $question->options
            ->where('is_correct', true)
            ->pluck('label')
            ->sort()
            ->values()
            ->toArray();

        $selectedSorted = collect($selectedLabels)->sort()->values()->toArray();

        $isCorrect = $selectedSorted === $correctLabels;

        $studentAnswer->update([
            'is_correct' => $isCorrect,
            'score' => $isCorrect ? $question->points : 0,
        ]);
    }

    /**
     * Grade Isian Singkat (short answer with keyword matching).
     * Case-insensitive match against any stored keyword.
     */
    private function gradeIsianSingkat(StudentAnswer $studentAnswer): void
    {
        $question = $studentAnswer->question;

        if ($studentAnswer->answer === null || trim($studentAnswer->answer) === '') {
            $studentAnswer->update(['is_correct' => false, 'score' => 0]);

            return;
        }

        $studentAnswerNormalized = mb_strtolower(trim($studentAnswer->answer));

        $isCorrect = $question->keywords->contains(function ($keyword) use ($studentAnswerNormalized) {
            return mb_strtolower(trim($keyword->keyword)) === $studentAnswerNormalized;
        });

        $studentAnswer->update([
            'is_correct' => $isCorrect,
            'score' => $isCorrect ? $question->points : 0,
        ]);
    }

    /**
     * Grade Menjodohkan (matching).
     * Score = (correct matches / total pairs) * points.
     */
    private function gradeMenjodohkan(StudentAnswer $studentAnswer): void
    {
        $question = $studentAnswer->question;
        $pairs = $question->matchingPairs;

        if ($studentAnswer->answer === null || $pairs->isEmpty()) {
            $studentAnswer->update(['is_correct' => false, 'score' => 0]);

            return;
        }

        $studentMatches = json_decode($studentAnswer->answer, true);
        if (! is_array($studentMatches)) {
            $studentAnswer->update(['is_correct' => false, 'score' => 0]);

            return;
        }

        $totalPairs = $pairs->count();
        $correctCount = 0;

        // Each pair's premise ID should be matched to the same pair's ID (correct response)
        foreach ($pairs as $pair) {
            $premiseId = (string) $pair->id;
            if (isset($studentMatches[$premiseId]) && (int) $studentMatches[$premiseId] === $pair->id) {
                $correctCount++;
            }
        }

        $ratio = $totalPairs > 0 ? $correctCount / $totalPairs : 0;
        $score = round((float) $question->points * $ratio, 2);
        $isCorrect = $correctCount === $totalPairs;

        $studentAnswer->update([
            'is_correct' => $isCorrect,
            'score' => $score,
        ]);
    }

    /**
     * Grade Ordering (sequence).
     * Full score if order matches exactly.
     */
    private function gradeOrdering(StudentAnswer $studentAnswer): void
    {
        $question = $studentAnswer->question;

        if ($studentAnswer->answer === null) {
            $studentAnswer->update(['is_correct' => false, 'score' => 0]);

            return;
        }

        $studentOrder = json_decode($studentAnswer->answer, true);
        if (! is_array($studentOrder)) {
            $studentAnswer->update(['is_correct' => false, 'score' => 0]);

            return;
        }

        // Correct order: options sorted by their `order` field
        $correctOrder = $question->options
            ->sortBy('order')
            ->pluck('id')
            ->toArray();

        $studentOrderInts = array_map('intval', $studentOrder);
        $isCorrect = $studentOrderInts === $correctOrder;

        $studentAnswer->update([
            'is_correct' => $isCorrect,
            'score' => $isCorrect ? $question->points : 0,
        ]);
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

        // Clear single-session exam lock
        \Illuminate\Support\Facades\Cache::forget("exam_session:{$attempt->id}:session_id");
    }
}
