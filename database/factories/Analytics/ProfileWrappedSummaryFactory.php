<?php

namespace Database\Factories\Analytics;

use App\Models\Analytics\ProfileWrappedSummary;
use App\Models\Content\Post;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProfileWrappedSummary>
 */
class ProfileWrappedSummaryFactory extends Factory
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
            'year' => now()->subYear()->year,
            'total_posts_published' => fake()->numberBetween(0, 250),
            'total_reactions_received' => fake()->numberBetween(0, 5000),
            'top_reaction_type' => fake()->randomElement(['love', 'cute', 'funny', 'wow', 'support']),
            'top_reaction_count' => fake()->numberBetween(0, 800),
            'most_active_month' => fake()->numberBetween(1, 12),
            'most_active_month_posts' => fake()->numberBetween(0, 60),
            'new_followers_count' => fake()->numberBetween(0, 1000),
            'pets_added_count' => fake()->numberBetween(0, 6),
            'most_engaged_post_id' => null,
            'most_engaged_post_score' => fake()->numberBetween(0, 2000),
            'share_image_path' => null,
            'generated_at' => now(),
            'share_image_generated_at' => null,
        ];
    }

    public function withMostEngagedPost(): static
    {
        return $this->state(fn (array $attributes): array => [
            'most_engaged_post_id' => Post::factory()->create([
                'user_id' => $attributes['user_id'] ?? User::factory(),
            ])->getKey(),
        ]);
    }
}
