<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\QuestionBank;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuestionBank>
 */
class QuestionBankFactory extends Factory
{
    protected $model = QuestionBank::class;

    public function definition(): array
    {
        return [
            'name' => fake()->sentence(3),
            'subject_id' => Subject::factory(),
            'user_id' => User::factory()->guru(),
            'description' => fake()->optional()->paragraph(),
        ];
    }
}
