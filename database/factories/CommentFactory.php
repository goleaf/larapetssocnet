<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Content\Comment;
use App\Models\Content\Post;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Comment>
 */
class CommentFactory extends Factory
{
    protected $model = Comment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $body = fake()->sentence();

        return [
            'post_id' => Post::factory(),
            'user_id' => User::factory(),
            'parent_id' => null,
            'body' => $body,
            'body_html' => $body,
            'gif_url' => null,
            'gif_preview_url' => null,
            'gif_title' => null,
            'gif_provider' => null,
            'language_code' => 'en',
            'quality_score' => 0,
            'depth' => 0,
            'is_pinned' => false,
            'edit_count' => 0,
            'replies_count' => 0,
            'reactions_count' => 0,
            'paw_count' => 0,
            'love_count' => 0,
        ];
    }
}
