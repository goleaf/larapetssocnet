<?php

declare(strict_types=1);

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
            'provider_user_id' => $provider.'_'.fake()->unique()->uuid(),
            'provider_avatar_url' => fake()->optional(0.6)->imageUrl(640, 640, 'pets', true),
            'provider_token' => Str::random(48),
            'provider_token_expires_at' => now()->addHour(),
        ];
    }
}
