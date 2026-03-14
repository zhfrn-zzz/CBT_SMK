<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ExamStatus;
use App\Models\AcademicYear;
use App\Models\ExamSession;
use App\Models\QuestionBank;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ExamSession>
 */
class ExamSessionFactory extends Factory
{
    protected $model = ExamSession::class;

    public function definition(): array
    {
        return [
            'name' => fake()->sentence(3),
            'subject_id' => Subject::factory(),
            'user_id' => User::factory()->guru(),
            'academic_year_id' => AcademicYear::factory()->active(),
            'question_bank_id' => QuestionBank::factory(),
            'token' => strtoupper(Str::random(6)),
            'duration_minutes' => 60,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHours(2),
            'is_randomize_questions' => false,
            'is_randomize_options' => false,
            'is_published' => true,
            'pool_count' => null,
            'kkm' => 75.00,
            'max_tab_switches' => 3,
            'status' => ExamStatus::Active,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'status' => ExamStatus::Active,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHours(2),
        ]);
    }

    public function scheduled(): static
    {
        return $this->state(fn () => [
            'status' => ExamStatus::Scheduled,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHours(2),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => ExamStatus::Completed,
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDays(2)->addHours(2),
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn () => [
            'status' => ExamStatus::Draft,
        ]);
    }
}
