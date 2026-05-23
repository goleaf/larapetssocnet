<?php

namespace Database\Factories\Security;

use App\Models\Identity\User;
use App\Models\Security\LoginSecurityAlert;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LoginSecurityAlert>
 */
class LoginSecurityAlertFactory extends Factory
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
            'token_hash' => hash('sha256', Str::random(64)),
            'country_code' => 'US',
            'country' => 'United States',
            'city' => 'Example City',
            'ip_address' => '203.0.113.'.fake()->numberBetween(1, 254),
            'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_0) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15',
            'device_type' => 'desktop',
            'browser_name' => 'Safari',
            'browser_version' => '17.0',
            'os_name' => 'Mac',
            'os_version' => '14.0',
            'login_at' => now(),
            'dismissed_at' => null,
            'secured_at' => null,
        ];
    }

    public function dismissed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'dismissed_at' => now(),
        ]);
    }

    public function secured(): static
    {
        return $this->state(fn (array $attributes): array => [
            'secured_at' => now(),
        ]);
    }
}
