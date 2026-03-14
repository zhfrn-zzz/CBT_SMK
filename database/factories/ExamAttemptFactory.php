<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ExamAttemptStatus;
use App\Models\ExamAttempt;
use App\Models\ExamSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamAttempt>
 */
class ExamAttemptFactory extends Factory
{
    protected $model = ExamAttempt::class;

    public function definition(): array
    {
        return [
            'exam_session_id' => ExamSession::factory(),
            'user_id' => User::factory()->siswa(),
            'started_at' => now(),
            'submitted_at' => null,
            'is_force_submitted' => false,
            'ip_address' => fake()->ipv4(),
            'status' => ExamAttemptStatus::InProgress,
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn () => [
            'status' => ExamAttemptStatus::Submitted,
            'submitted_at' => now(),
        ]);
    }

    public function graded(): static
    {
        return $this->state(fn () => [
            'status' => ExamAttemptStatus::Graded,
            'submitted_at' => now(),
            'is_fully_graded' => true,
            'score' => fake()->randomFloat(2, 0, 100),
        ]);
    }
}
