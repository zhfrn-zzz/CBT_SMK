<?php

namespace Database\Factories;

use App\Models\ForumCategory;
use App\Models\ForumThread;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ForumThread> */
class ForumThreadFactory extends Factory
{
    protected $model = ForumThread::class;

    public function definition(): array
    {
        return [
            'forum_category_id' => ForumCategory::factory(),
            'user_id' => User::factory(),
            'title' => fake()->sentence(),
            'content' => fake()->paragraphs(2, true),
            'is_pinned' => false,
            'is_locked' => false,
            'last_reply_at' => null,
            'reply_count' => 0,
            'view_count' => 0,
        ];
    }

    public function pinned(): static
    {
        return $this->state(fn () => ['is_pinned' => true]);
    }

    public function locked(): static
    {
        return $this->state(fn () => ['is_locked' => true]);
    }
}
