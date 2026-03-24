<?php

declare(strict_types=1);

namespace App\Services\Exam;

use App\Models\ExamSession;
use App\Models\Question;
use Illuminate\Support\Collection;

class ExamRandomizerService
{
    /**
     * Ambil dan randomize soal untuk satu siswa.
     *
     * @return Collection<int, array{question_id: int, order: int, option_order: array|null}>
     */
    public function generateQuestionSet(ExamSession $examSession, ?int $studentId = null): Collection
    {
        $questions = $this->getQuestions($examSession, $studentId);

        if ($questions->isEmpty()) {
            throw new \RuntimeException('Tidak ada soal tersedia untuk ujian ini. Pastikan bank soal memiliki soal.');
        }

        if ($examSession->is_randomize_questions) {
            $questions = $questions->shuffle();
        }

        return $questions->values()->map(function (Question $question, int $index) use ($examSession) {
            $optionOrder = null;

            if ($examSession->is_randomize_options && $question->options->isNotEmpty()) {
                $optionOrder = $question->options->pluck('label')->shuffle()->values()->toArray();
            }

            return [
                'question_id' => $question->id,
                'order' => $index + 1,
                'option_order' => $optionOrder,
            ];
        });
    }

    /**
     * Ambil soal dari bank. Jika pool_count di-set, ambil subset random.
     * Uses deterministic seed (exam_session_id + student_id) for reproducibility.
     */
    private function getQuestions(ExamSession $examSession, ?int $studentId = null): Collection
    {
        // Jika ada soal yang dipilih manual di exam_session_questions, gunakan itu
        if ($examSession->questions()->count() > 0) {
            return $examSession->questions()->with('options')->get();
        }

        // Fallback: ambil dari question bank
        $query = $examSession->questionBank->questions()->with('options');

        $allQuestions = $query->get();

        // Jika pool_count di-set, ambil subset random (clamp to available)
        if ($examSession->pool_count && $allQuestions->isNotEmpty()) {
            $poolCount = min($examSession->pool_count, $allQuestions->count());

            // Deterministic seed per student for reproducibility
            if ($studentId) {
                $seed = crc32("{$examSession->id}:{$studentId}");
                $allQuestions = $allQuestions->shuffle($seed);

                return $allQuestions->take($poolCount);
            }

            return $allQuestions->random($poolCount);
        }

        return $allQuestions;
    }
}
