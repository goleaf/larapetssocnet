<?php

namespace Database\Factories\Security;

use App\Models\Identity\User;
use App\Models\Security\AuthAuditLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuthAuditLog>
 */
class AuthAuditLogFactory extends Factory
{
    protected $model = AuthAuditLog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'event_type' => fake()->randomElement([
                'login_success',
                'login_failure',
                'two_factor_challenge_failed',
                'two_factor_challenge_passed',
            ]),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'country' => fake()->optional(0.4)->countryCode(),
            'city' => fake()->optional(0.4)->city(),
            'additional_data' => [
                'identifier_hash' => fake()->sha1(),
            ],
        ];
    }
}
