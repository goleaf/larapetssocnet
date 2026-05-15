<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Identity\User;
use App\Models\Pets\Pet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pet>
 */
class PetFactory extends Factory
{
    protected $model = Pet::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->firstName(),
            'slug' => null,
            'species' => fake()->randomElement(['dog', 'cat', 'bird', 'rabbit', 'hamster']),
            'breed' => fake()->optional(0.8)->word(),
            'sex' => fake()->randomElement(['male', 'female']),
            'birth_date' => fake()->optional(0.85)->date(),
            'bio' => fake()->optional()->sentence(),
            'avatar_path' => fake()->optional(0.5)->imageUrl(640, 640, 'pets', true),
            'is_public' => true,
            'adoption_status' => 'not_listed',
            'followers_count' => 0,
            'posts_count' => 0,
        ];
    }
}
