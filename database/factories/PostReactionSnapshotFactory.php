<?php

namespace Database\Factories;

use App\Models\Content\Post;
use App\Models\Content\PostReactionSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PostReactionSnapshot>
 */
class PostReactionSnapshotFactory extends Factory
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
            'captured_at' => now()->subMinutes(fake()->numberBetween(5, 60))->startOfMinute(),
            'reactions_count' => fake()->numberBetween(0, 200),
        ];
    }
}
