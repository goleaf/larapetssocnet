<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'bio' => fake()->optional()->sentence(),
            'avatar_path' => fake()->optional(0.35)->imageUrl(640, 640, 'pets', true),
            'city' => fake()->optional(0.8)->city(),
            'country_code' => fake()->optional(0.8)->countryCode(),
            'is_private' => fake()->boolean(15),
            'last_seen_at' => fake()->dateTimeBetween('-7 days', 'now'),
            'onboarding_step' => fake()->randomElement(['welcome', 'profile', 'pets', 'complete']),
            'interests_text' => implode(', ', fake()->words(fake()->numberBetween(3, 7))),
            'followers_count' => 0,
            'following_count' => 0,
            'pets_count' => 0,
            'posts_count' => 0,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
