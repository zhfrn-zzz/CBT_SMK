<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Department;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subject>
 */
class SubjectFactory extends Factory
{
    protected $model = Subject::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'code' => fake()->unique()->lexify('???'),
            'department_id' => null,
        ];
    }
}
