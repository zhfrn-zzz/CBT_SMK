<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\StudentAnswer;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StudentAnswer> */
class StudentAnswerFactory extends Factory
{
    protected $model = StudentAnswer::class;

    public function definition(): array
    {
        return [
            'exam_attempt_id' => ExamAttempt::factory(),
            'question_id' => Question::factory(),
            'answer' => fake()->randomElement(['A', 'B', 'C', 'D']),
            'is_flagged' => false,
            'is_correct' => fake()->boolean(),
            'score' => fake()->randomFloat(2, 0, 100),
            'feedback' => null,
            'answered_at' => now(),
        ];
    }

    /**
     * Create answer for a specific attempt, ensuring the question belongs to
     * the same exam session via attempt questions.
     */
    public function forAttempt(ExamAttempt $attempt): static
    {
        return $this->state(function () use ($attempt) {
            $questionId = $attempt->attemptQuestions()->inRandomOrder()->value('question_id');

            return [
                'exam_attempt_id' => $attempt->id,
                'question_id' => $questionId ?? Question::factory(),
            ];
        });
    }
}
