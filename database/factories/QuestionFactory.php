<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\QuestionType;
use App\Models\Question;
use App\Models\QuestionBank;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Question>
 */
class QuestionFactory extends Factory
{
    protected $model = Question::class;

    public function definition(): array
    {
        return [
            'question_bank_id' => QuestionBank::factory(),
            'type' => QuestionType::PilihanGanda,
            'content' => '<p>'.fake()->sentence().'</p>',
            'points' => 2,
            'explanation' => fake()->sentence(),
            'order' => fake()->numberBetween(1, 100),
        ];
    }

    public function pilihanGanda(): static
    {
        return $this->state(fn () => [
            'type' => QuestionType::PilihanGanda,
        ]);
    }

    public function benarSalah(): static
    {
        return $this->state(fn () => [
            'type' => QuestionType::BenarSalah,
        ]);
    }

    public function esai(): static
    {
        return $this->state(fn () => [
            'type' => QuestionType::Esai,
            'points' => 10,
        ]);
    }
}
