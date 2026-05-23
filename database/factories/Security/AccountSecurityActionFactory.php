<?php

namespace Database\Factories\Security;

use App\Models\Identity\User;
use App\Models\Security\AccountSecurityAction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AccountSecurityAction>
 */
class AccountSecurityActionFactory extends Factory
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
            'action_type' => AccountSecurityAction::ACTION_PASSWORD_RESET_EMERGENCY_LOCK,
            'token_hash' => hash('sha256', Str::random(64)),
            'used_at' => null,
            'expires_at' => now()->addDays(7),
        ];
    }

    public function used(): static
    {
        return $this->state(fn (array $attributes): array => [
            'used_at' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'expires_at' => now()->subMinute(),
        ]);
    }
}
