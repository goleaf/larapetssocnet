<?php

namespace Database\Factories\Content;

use App\Models\Content\PostDraft;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PostDraft>
 */
class PostDraftFactory extends Factory
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
            'context_type' => 'default',
            'context_id' => 0,
            'body' => fake()->optional()->paragraph(),
            'visibility' => 'public',
            'mood' => fake()->optional()->randomElement(['happy', 'curious', 'sleepy', 'playful']),
            'location' => fake()->optional()->city(),
            'location_lat' => fake()->optional()->latitude(),
            'location_lng' => fake()->optional()->longitude(),
            'tagged_pets' => [],
            'media_payload' => [],
            'link_preview' => null,
            'scheduled_publish_at' => null,
            'last_autosaved_at' => now(),
        ];
    }
}
