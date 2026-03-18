<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SubmissionType;
use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Assignment> */
class AssignmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'subject_id' => Subject::factory(),
            'classroom_id' => Classroom::factory(),
            'user_id' => User::factory()->guru(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'deadline_at' => now()->addDays(7),
            'max_score' => 100,
            'allow_late_submission' => false,
            'late_penalty_percent' => 0,
            'submission_type' => SubmissionType::FileOrText,
            'is_published' => true,
        ];
    }

    public function overdue(): static
    {
        return $this->state(fn () => ['deadline_at' => now()->subDay()]);
    }

    public function latePenalty(int $percent = 10): static
    {
        return $this->state(fn () => [
            'allow_late_submission' => true,
            'late_penalty_percent' => $percent,
        ]);
    }
}
