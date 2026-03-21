<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Announcement;
use App\Models\Classroom;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Announcement>
 */
class AnnouncementFactory extends Factory
{
    protected $model = Announcement::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->guru(),
            'classroom_id' => null,
            'subject_id' => null,
            'title' => fake()->sentence(4),
            'content' => '<p>'.fake()->paragraphs(2, true).'</p>',
            'is_pinned' => false,
            'is_public' => false,
            'published_at' => now(),
        ];
    }

    public function public(): static
    {
        return $this->state(fn () => ['is_public' => true]);
    }

    public function pinned(): static
    {
        return $this->state(fn () => ['is_pinned' => true]);
    }

    public function unpublished(): static
    {
        return $this->state(fn () => ['published_at' => now()->addDay()]);
    }

    public function forClassroom(Classroom $classroom): static
    {
        return $this->state(fn () => ['classroom_id' => $classroom->id]);
    }
}
