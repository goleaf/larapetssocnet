<?php

namespace Database\Factories\Content;

use App\Models\Content\Comment;
use App\Models\Content\CommentThreadSubscription;
use App\Models\Content\Post;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommentThreadSubscription>
 */
class CommentThreadSubscriptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'post_id' => Post::factory(),
            'root_comment_id' => Comment::factory(),
            'subscribed_at' => now(),
            'unsubscribed_at' => null,
        ];
    }
}
