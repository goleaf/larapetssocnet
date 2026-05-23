<?php

namespace Database\Factories\Identity;

use App\Models\Identity\SocialAccount;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SocialAccount>
 */
class SocialAccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $provider = fake()->randomElement(['google', 'facebook']);

        return [
            'user_id' => User::factory(),
            'provider' => $provider,
            'provider_id' => $provider.'_'.fake()->unique()->uuid(),
            'provider_email' => fake()->safeEmail(),
            'provider_nickname' => fake()->optional(0.5)->userName(),
            'provider_name' => fake()->name(),
            'avatar_url' => fake()->optional(0.6)->imageUrl(256, 256, 'people', true),
            'token' => Str::random(48),
            'refresh_token' => Str::random(64),
            'expires_at' => now()->addHour(),
            'provider_payload' => [
                'locale' => fake()->locale(),
                'verified_email' => fake()->boolean(80),
            ],
        ];
    }
}
