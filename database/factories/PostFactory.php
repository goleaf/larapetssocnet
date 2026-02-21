<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Post>
 */
class PostFactory extends Factory
{
    protected $model = \App\Models\Post::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $body = fake()->paragraph(fake()->numberBetween(1, 3));

        return [
            'user_id' => \App\Models\User::factory(),
            'pet_id' => null,
            'body' => $body,
            'body_html' => '<p>'.$body.'</p>',
            'type' => 'text',
            'visibility' => fake()->randomElement(['public', 'followers', 'private']),
            'status' => 'published',
            'likes_count' => 0,
            'comments_count' => 0,
            'reactions_count' => 0,
            'shares_count' => 0,
            'published_at' => fake()->dateTimeBetween('-60 days', 'now'),
        ];
    }
}
