<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MaterialType;
use App\Models\Classroom;
use App\Models\Material;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Material> */
class MaterialFactory extends Factory
{
    public function definition(): array
    {
        return [
            'subject_id' => Subject::factory(),
            'classroom_id' => Classroom::factory(),
            'user_id' => User::factory()->guru(),
            'title' => fake()->sentence(4),
            'description' => fake()->sentence(),
            'type' => MaterialType::Text,
            'text_content' => fake()->paragraph(),
            'topic' => fake()->word(),
            'order' => fake()->numberBetween(0, 10),
            'is_published' => true,
        ];
    }

    public function file(): static
    {
        return $this->state(fn () => [
            'type' => MaterialType::File,
            'file_path' => 'materials/1/1/test.pdf',
            'file_original_name' => 'test.pdf',
            'file_size' => 1024,
            'text_content' => null,
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn () => ['is_published' => false]);
    }
}
