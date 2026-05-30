<?php

namespace Database\Factories\Content;

use App\Models\Content\Comment;
use App\Models\Content\CommentTranslation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommentTranslation>
 */
class CommentTranslationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'comment_id' => Comment::factory(),
            'source_language' => 'es',
            'target_language' => 'en',
            'translated_body' => fake()->sentence(),
            'provider' => 'test',
            'cached_at' => now(),
        ];
    }
}
