<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Enums\QuestionType;
use App\Jobs\ComputeItemAnalysisJob;
use App\Models\ExamAttempt;
use App\Models\ExamSession;
use App\Models\ItemAnalysisCache;
use Illuminate\Support\Collection;

class ItemAnalysisService
{
    /**
     * Get or compute analysis — returns stale/cached data or triggers background computation.
     *
     * @return array{computing: bool, computed_at: string|null, items: array, summary: array, kd_breakdown: array}
     */
    public function getOrComputeAnalysis(ExamSession $examSession): array
    {
        $cache = ItemAnalysisCache::where('exam_session_id', $examSession->id)->first();

        $isStale = $cache === null || $cache->computed_at->lt(now()->subHour());

        if ($isStale) {
            ComputeItemAnalysisJob::dispatch($examSession);
        }

        if ($cache === null) {
            return [
                'computing' => true,
                'computed_at' => null,
                'items' => [],
                'summary' => [
                    'total_questions' => 0,
                    'easy_count' => 0,
                    'medium_count' => 0,
                    'hard_count' => 0,
                    'good_discrimination_count' => 0,
                    'fair_discrimination_count' => 0,
                    'poor_discrimination_count' => 0,
                ],
                'kd_breakdown' => [],
            ];
        }

        $data = $cache->analysis_data;

        return array_merge($data, ['computing' => $isStale]);
    }

    /**
     * Analyze an exam session and return structured item analysis data.
     *
     * @return array{computed_at: string, items: array, summary: array, kd_breakdown: array}
     */
    public function analyzeExamSession(ExamSession $examSession): array
    {
        $attempts = ExamAttempt::where('exam_session_id', $examSession->id)
            ->whereIn('status', ['submitted', 'graded'])
            ->with(['answers.question.options', 'answers.question.competencyStandards'])
            ->get();

        $totalAttempts = $attempts->count();

        if ($totalAttempts === 0) {
            return $this->buildEmptyResult($examSession);
        }

        $studentScores = $attempts->map(fn (ExamAttempt $attempt) => (float) ($attempt->score ?? 0));
        $meanTotal = $studentScores->avg();
        $stdDev = $this->computeStdDev($studentScores, $meanTotal);

        $questionMap = $this->buildQuestionMap($attempts);

        $items = [];
        foreach ($questionMap as $questionId => $questionData) {
            $items[] = $this->analyzeQuestion($questionData, $studentScores, $attempts, $meanTotal, $stdDev, $totalAttempts);
        }

        usort($items, fn ($a, $b) => $a['order'] <=> $b['order']);

        $summary = $this->buildSummary($items);
        $kdBreakdown = $this->buildKdBreakdown($items, $examSession);

        return [
            'exam_session_id' => $examSession->id,
            'computed_at' => now()->toISOString(),
            'items' => $items,
            'summary' => $summary,
            'kd_breakdown' => $kdBreakdown,
        ];
    }

    /**
     * Get per-KD breakdown from cached analysis.
     */
    public function getKdBreakdown(ExamSession $examSession, ?int $userId = null): array
    {
        $cache = ItemAnalysisCache::where('exam_session_id', $examSession->id)->first();
        if (! $cache) {
            return [];
        }

        return $cache->analysis_data['kd_breakdown'] ?? [];
    }

    private function buildQuestionMap(Collection $attempts): array
    {
        $questionMap = [];
        foreach ($attempts as $attempt) {
            foreach ($attempt->answers as $answer) {
                $q = $answer->question;
                $qId = $q->id;
                if (! isset($questionMap[$qId])) {
                    $questionMap[$qId] = [
                        'question' => $q,
                        'answers' => [],
                    ];
                }
                $questionMap[$qId]['answers'][] = [
                    'attempt_id' => $attempt->id,
                    'answer' => $answer->answer,
                    'is_correct' => $answer->is_correct,
                    'score' => $answer->score,
                ];
            }
        }

        return $questionMap;
    }

    private function analyzeQuestion(array $questionData, Collection $studentScores, Collection $attempts, float $meanTotal, float $stdDev, int $totalAttempts): array
    {
        $question = $questionData['question'];
        $answers = $questionData['answers'];
        $type = $question->type;

        $isEssayType = in_array($type, [QuestionType::Esai, QuestionType::IsianSingkat]);
        $hasUngraded = $isEssayType && collect($answers)->contains(fn ($a) => $a['is_correct'] === null && $a['score'] === null);

        if ($hasUngraded) {
            return $this->buildSkippedItem($question, $totalAttempts, count($answers));
        }

        $correctCount = collect($answers)->filter(fn ($a) => $a['is_correct'] === true)->count();
        $answerCount = count($answers);

        $p = $answerCount > 0 ? $correctCount / $answerCount : 0.0;
        $d = $this->computeDiscrimination($p, $attempts, $answers, $meanTotal, $stdDev);

        $choiceDistribution = null;
        if ($type === QuestionType::PilihanGanda) {
            $choiceDistribution = $this->buildChoiceDistribution($question, $answers, $answerCount);
        }

        $competencyStandards = $question->competencyStandards->map(fn ($ks) => [
            'code' => $ks->code,
            'name' => $ks->name,
        ])->toArray();

        $order = $question->pivot?->order ?? $question->order ?? 0;

        return [
            'question_id' => $question->id,
            'order' => $order,
            'content_preview' => mb_substr(strip_tags($question->content), 0, 100),
            'type' => $type->value,
            'total_attempts' => $answerCount,
            'correct_count' => $correctCount,
            'difficulty_index' => round($p, 4),
            'difficulty_label' => $this->difficultyLabel($p),
            'discrimination_index' => round($d, 4),
            'discrimination_label' => $this->discriminationLabel($d),
            'choice_distribution' => $choiceDistribution,
            'competency_standards' => $competencyStandards,
            'skipped' => false,
        ];
    }

    private function computeDiscrimination(float $p, Collection $attempts, array $answers, float $meanTotal, float $stdDev): float
    {
        if ($p <= 0.0 || $p >= 1.0 || $stdDev == 0.0) {
            return 0.0;
        }

        $q = 1.0 - $p;

        $attemptScores = $attempts->keyBy('id')->map(fn ($a) => (float) ($a->score ?? 0));

        $correctAttemptIds = collect($answers)->filter(fn ($a) => $a['is_correct'] === true)->pluck('attempt_id');

        if ($correctAttemptIds->isEmpty()) {
            return 0.0;
        }

        $mpValues = $correctAttemptIds->map(fn ($id) => $attemptScores[$id] ?? 0.0);
        $mp = $mpValues->avg();

        $rpb = (($mp - $meanTotal) / $stdDev) * sqrt($p * $q);

        return max(-1.0, min(1.0, $rpb));
    }

    private function computeStdDev(Collection $scores, float $mean): float
    {
        if ($scores->count() <= 1) {
            return 0.0;
        }
        $variance = $scores->map(fn ($s) => pow($s - $mean, 2))->avg();

        return sqrt($variance);
    }

    private function buildChoiceDistribution(mixed $question, array $answers, int $answerCount): array
    {
        $distribution = [];
        foreach ($question->options as $option) {
            $count = collect($answers)->filter(fn ($a) => $a['answer'] === $option->label)->count();
            $distribution[] = [
                'label' => $option->label,
                'count' => $count,
                'percentage' => $answerCount > 0 ? round($count / $answerCount * 100, 1) : 0.0,
                'is_correct' => $option->is_correct,
            ];
        }

        return $distribution;
    }

    private function buildSkippedItem(mixed $question, int $totalAttempts, int $answerCount): array
    {
        return [
            'question_id' => $question->id,
            'order' => $question->order ?? 0,
            'content_preview' => mb_substr(strip_tags($question->content), 0, 100),
            'type' => $question->type->value,
            'total_attempts' => $answerCount,
            'correct_count' => 0,
            'difficulty_index' => 0.0,
            'difficulty_label' => 'sedang',
            'discrimination_index' => 0.0,
            'discrimination_label' => 'buruk',
            'choice_distribution' => null,
            'competency_standards' => $question->competencyStandards->map(fn ($ks) => ['code' => $ks->code, 'name' => $ks->name])->toArray(),
            'skipped' => true,
        ];
    }

    private function buildEmptyResult(ExamSession $examSession): array
    {
        return [
            'exam_session_id' => $examSession->id,
            'computed_at' => now()->toISOString(),
            'items' => [],
            'summary' => [
                'total_questions' => 0,
                'easy_count' => 0,
                'medium_count' => 0,
                'hard_count' => 0,
                'good_discrimination_count' => 0,
                'fair_discrimination_count' => 0,
                'poor_discrimination_count' => 0,
            ],
            'kd_breakdown' => [],
        ];
    }

    private function buildSummary(array $items): array
    {
        $notSkipped = array_filter($items, fn ($i) => ! $i['skipped']);

        return [
            'total_questions' => count($items),
            'easy_count' => count(array_filter($notSkipped, fn ($i) => $i['difficulty_label'] === 'mudah')),
            'medium_count' => count(array_filter($notSkipped, fn ($i) => $i['difficulty_label'] === 'sedang')),
            'hard_count' => count(array_filter($notSkipped, fn ($i) => $i['difficulty_label'] === 'sulit')),
            'good_discrimination_count' => count(array_filter($notSkipped, fn ($i) => $i['discrimination_label'] === 'baik')),
            'fair_discrimination_count' => count(array_filter($notSkipped, fn ($i) => $i['discrimination_label'] === 'cukup')),
            'poor_discrimination_count' => count(array_filter($notSkipped, fn ($i) => $i['discrimination_label'] === 'buruk')),
        ];
    }

    private function buildKdBreakdown(array $items, ExamSession $examSession): array
    {
        $kdMap = [];
        foreach ($items as $item) {
            foreach ($item['competency_standards'] as $kd) {
                $key = $kd['code'];
                if (! isset($kdMap[$key])) {
                    $kdMap[$key] = [
                        'code' => $kd['code'],
                        'name' => $kd['name'],
                        'question_count' => 0,
                        'total_difficulty' => 0.0,
                    ];
                }
                $kdMap[$key]['question_count']++;
                $kdMap[$key]['total_difficulty'] += $item['difficulty_index'];
            }
        }

        return array_values(array_map(function ($kd) {
            $avg = $kd['question_count'] > 0 ? $kd['total_difficulty'] / $kd['question_count'] : 0.0;

            return [
                'code' => $kd['code'],
                'name' => $kd['name'],
                'question_count' => $kd['question_count'],
                'avg_score' => round($avg * 100, 1),
                'max_possible' => 100,
            ];
        }, $kdMap));
    }

    private function difficultyLabel(float $p): string
    {
        if ($p > 0.70) {
            return 'mudah';
        }
        if ($p >= 0.30) {
            return 'sedang';
        }

        return 'sulit';
    }

    private function discriminationLabel(float $d): string
    {
        if ($d > 0.4) {
            return 'baik';
        }
        if ($d >= 0.2) {
            return 'cukup';
        }

        return 'buruk';
    }
}
