<?php

namespace Database\Factories\Content;

use App\Models\Content\Post;
use App\Models\Content\PostReaction;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PostReaction>
 */
class PostReactionFactory extends Factory
{
    protected $model = PostReaction::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'post_id' => Post::factory(),
            'user_id' => User::factory(),
            'type' => fake()->randomElement(PostReaction::TYPES),
        ];
    }
}
