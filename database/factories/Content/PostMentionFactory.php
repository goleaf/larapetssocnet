<?php

namespace Database\Factories\Content;

use App\Models\Content\Post;
use App\Models\Content\PostMention;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PostMention>
 */
class PostMentionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'post_id' => Post::factory(),
            'mentioned_user_id' => User::factory(),
            'mentioned_username' => fake()->userName(),
        ];
    }
}
