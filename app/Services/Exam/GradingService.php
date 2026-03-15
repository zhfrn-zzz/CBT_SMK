<?php

declare(strict_types=1);

namespace App\Services\Exam;

use App\Enums\ExamAttemptStatus;
use App\Enums\QuestionType;
use App\Models\ExamAttempt;
use App\Models\ExamSession;
use App\Models\StudentAnswer;
use Illuminate\Support\Facades\DB;

class GradingService
{
    /**
     * Save manual grade for a single student answer.
     */
    public function saveGrade(StudentAnswer $studentAnswer, float $score, ?string $feedback): void
    {
        $maxPoints = (float) $studentAnswer->question->points;
        $score = min($score, $maxPoints);
        $score = max(0, $score);

        $studentAnswer->update([
            'score' => $score,
            'is_correct' => $score > 0,
            'feedback' => $feedback,
        ]);

        $this->recalculateAttemptScore($studentAnswer->attempt);
    }

    /**
     * Recalculate attempt score after grading.
     */
    public function recalculateAttemptScore(ExamAttempt $attempt): void
    {
        $answers = $attempt->answers()->with('question')->get();

        $totalScore = 0;
        $maxScore = 0;
        $allGraded = true;

        foreach ($answers as $answer) {
            $maxScore += (float) $answer->question->points;

            if ($answer->score !== null) {
                $totalScore += (float) $answer->score;
            } else {
                // Check if it's a type that needs manual grading
                if ($answer->question->type === QuestionType::Esai) {
                    $allGraded = false;
                }
            }
        }

        $percentage = $maxScore > 0 ? ($totalScore / $maxScore) * 100 : 0;

        $attempt->update([
            'score' => round($percentage, 2),
            'is_fully_graded' => $allGraded,
            'status' => $allGraded ? ExamAttemptStatus::Graded : ExamAttemptStatus::Submitted,
        ]);
    }

    /**
     * Get grading progress for an exam session.
     */
    public function getGradingProgress(ExamSession $examSession): array
    {
        $attempts = $examSession->attempts()
            ->where('status', '!=', ExamAttemptStatus::InProgress)
            ->get();

        $totalAttempts = $attempts->count();
        $fullyGraded = $attempts->where('is_fully_graded', true)->count();

        // Count ungraded essay answers
        $ungradedEssays = StudentAnswer::whereIn('exam_attempt_id', $attempts->pluck('id'))
            ->whereHas('question', fn ($q) => $q->where('type', QuestionType::Esai))
            ->whereNull('score')
            ->count();

        return [
            'total_attempts' => $totalAttempts,
            'fully_graded' => $fullyGraded,
            'ungraded_essays' => $ungradedEssays,
        ];
    }

    /**
     * Get exam result statistics.
     */
    public function getExamStatistics(ExamSession $examSession): array
    {
        $attempts = $examSession->attempts()
            ->whereIn('status', [ExamAttemptStatus::Submitted, ExamAttemptStatus::Graded])
            ->get();

        if ($attempts->isEmpty()) {
            return [
                'average' => 0,
                'highest' => 0,
                'lowest' => 0,
                'total_students' => 0,
                'passed' => 0,
                'failed' => 0,
            ];
        }

        $scores = $attempts->pluck('score')->filter(fn ($s) => $s !== null);
        $kkm = (float) ($examSession->kkm ?? 0);

        return [
            'average' => $scores->isNotEmpty() ? round((float) $scores->avg(), 2) : 0,
            'highest' => $scores->isNotEmpty() ? round((float) $scores->max(), 2) : 0,
            'lowest' => $scores->isNotEmpty() ? round((float) $scores->min(), 2) : 0,
            'total_students' => $attempts->count(),
            'passed' => $kkm > 0 ? $scores->filter(fn ($s) => (float) $s >= $kkm)->count() : 0,
            'failed' => $kkm > 0 ? $scores->filter(fn ($s) => (float) $s < $kkm)->count() : 0,
        ];
    }

    /**
     * Generate CSV content for exam results export.
     */
    public function generateExportCsv(ExamSession $examSession): string
    {
        $examSession->load(['subject', 'attempts' => function ($q) {
            $q->whereIn('status', [ExamAttemptStatus::Submitted, ExamAttemptStatus::Graded])
                ->with(['user', 'answers.question'])
                ->orderBy('id');
        }]);

        $kkm = (float) ($examSession->kkm ?? 0);
        $output = fopen('php://temp', 'r+');

        // BOM for Excel UTF-8
        fwrite($output, "\xEF\xBB\xBF");

        // Header
        fputcsv($output, [
            'No',
            'Nama Siswa',
            'Username',
            'Mulai',
            'Selesai',
            'Nilai',
            'Status',
            'Dinilai Lengkap',
        ]);

        $no = 1;
        foreach ($examSession->attempts as $attempt) {
            $status = '-';
            if ($kkm > 0 && $attempt->score !== null) {
                $status = (float) $attempt->score >= $kkm ? 'Lulus' : 'Remedial';
            }

            fputcsv($output, [
                $no++,
                $attempt->user->name,
                $attempt->user->username,
                $attempt->started_at?->format('Y-m-d H:i'),
                $attempt->submitted_at?->format('Y-m-d H:i'),
                $attempt->score !== null ? number_format((float) $attempt->score, 2) : '-',
                $status,
                $attempt->is_fully_graded ? 'Ya' : 'Belum',
            ]);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }
}
