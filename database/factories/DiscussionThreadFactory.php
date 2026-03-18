<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Classroom;
use App\Models\DiscussionThread;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DiscussionThread> */
class DiscussionThreadFactory extends Factory
{
    public function definition(): array
    {
        return [
            'subject_id' => Subject::factory(),
            'classroom_id' => Classroom::factory(),
            'user_id' => User::factory()->guru(),
            'title' => fake()->sentence(5),
            'content' => fake()->paragraph(),
            'is_pinned' => false,
            'is_locked' => false,
            'reply_count' => 0,
        ];
    }

    public function locked(): static
    {
        return $this->state(fn () => ['is_locked' => true]);
    }
}
