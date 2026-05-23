<?php

namespace Database\Factories\Security;

use App\Models\Identity\User;
use App\Models\Security\MagicLoginToken;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MagicLoginToken>
 */
class MagicLoginTokenFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $token = Str::random(64);

        return [
            'user_id' => User::factory(),
            'token' => Str::random(40),
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addMinutes(15),
            'used_at' => null,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'expires_at' => now()->subMinute(),
        ]);
    }

    public function consumed(): static
    {
        return $this->state(fn (): array => [
            'used_at' => now()->subMinute(),
        ]);
    }
}
