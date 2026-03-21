<?php

namespace Database\Factories;

use App\Models\ForumReply;
use App\Models\ForumThread;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ForumReply> */
class ForumReplyFactory extends Factory
{
    protected $model = ForumReply::class;

    public function definition(): array
    {
        return [
            'forum_thread_id' => ForumThread::factory(),
            'user_id' => User::factory(),
            'content' => fake()->paragraphs(1, true),
        ];
    }
}
