<?php

namespace Database\Factories\Content;

use App\Models\Content\CommentDraft;
use App\Models\Content\Post;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommentDraft>
 */
class CommentDraftFactory extends Factory
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
            'body' => fake()->sentence(),
            'gif_url' => null,
            'gif_preview_url' => null,
            'gif_title' => null,
            'gif_provider' => null,
            'last_autosaved_at' => now(),
        ];
    }
}
