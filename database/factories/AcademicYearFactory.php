<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Semester;
use App\Models\AcademicYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AcademicYear>
 */
class AcademicYearFactory extends Factory
{
    protected $model = AcademicYear::class;

    public function definition(): array
    {
        $year = fake()->numberBetween(2024, 2026);

        return [
            'name' => "{$year}/".($year + 1),
            'semester' => fake()->randomElement(Semester::cases()),
            'is_active' => false,
            'starts_at' => "{$year}-07-01",
            'ends_at' => ($year + 1).'-06-30',
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'is_active' => true,
        ]);
    }
}
