<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\GradeLevel;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Classroom>
 */
class ClassroomFactory extends Factory
{
    protected $model = Classroom::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'academic_year_id' => AcademicYear::factory(),
            'department_id' => Department::factory(),
            'grade_level' => fake()->randomElement(GradeLevel::cases()),
        ];
    }
}
