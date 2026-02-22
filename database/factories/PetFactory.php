<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Pet>
 */
class PetFactory extends Factory
{
    protected $model = \App\Models\Pet::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => \App\Models\User::factory(),
            'name' => fake()->firstName(),
            'slug' => fake()->unique()->slug(2),
            'species' => fake()->randomElement(['dog', 'cat', 'bird', 'rabbit', 'hamster']),
            'breed' => fake()->optional(0.8)->word(),
            'sex' => fake()->randomElement(['male', 'female']),
            'birth_date' => fake()->optional(0.85)->date(),
            'bio' => fake()->optional()->sentence(),
            'avatar_path' => fake()->optional(0.5)->imageUrl(640, 640, 'pets', true),
            'is_public' => fake()->boolean(90),
            'adoption_status' => 'not_listed',
            'followers_count' => 0,
            'posts_count' => 0,
        ];
    }
}
