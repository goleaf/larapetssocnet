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
        $body = fake()->optional()->paragraph();
        $visibility = 'public';
        $mood = fake()->optional()->randomElement(['happy', 'excited', 'proud', 'worried', 'sad', 'grateful', 'playful']);
        $location = fake()->optional()->city();

        return [
            'user_id' => User::factory(),
            'context_type' => 'default',
            'context_id' => 0,
            'body' => $body,
            'visibility' => $visibility,
            'mood' => $mood,
            'location' => $location,
            'location_lat' => fake()->optional()->latitude(),
            'location_lng' => fake()->optional()->longitude(),
            'tagged_pets' => [],
            'media_payload' => [],
            'link_preview' => null,
            'state' => [
                'text_content' => $body,
                'temporary_file_paths' => [],
                'attachment_metadata' => [],
                'selected_pet_ids' => [],
                'location_display_text' => $location,
                'location_lat' => null,
                'location_lng' => null,
                'selected_mood' => $mood,
                'selected_visibility' => $visibility,
                'scheduled_publish_at' => null,
                'link_preview' => [],
                'context_type' => 'default',
                'context_id' => 0,
            ],
            'scheduled_publish_at' => null,
            'last_autosaved_at' => now(),
        ];
    }
}
