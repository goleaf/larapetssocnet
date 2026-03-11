<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UsernameChange;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<UsernameChange>
 */
class UsernameChangeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $oldUsername = (string) Str::of(fake()->unique()->userName())
            ->lower()
            ->replaceMatches('/[^a-z0-9_]/', '')
            ->trim('_')
            ->limit(30, '');

        if ($oldUsername === '') {
            $oldUsername = 'user_'.fake()->unique()->numerify('###');
        }

        $newUsername = (string) Str::of(fake()->unique()->userName())
            ->lower()
            ->replaceMatches('/[^a-z0-9_]/', '')
            ->trim('_')
            ->limit(30, '');

        if ($newUsername === '' || $newUsername === $oldUsername) {
            $newUsername = 'user_'.fake()->unique()->numerify('###');
        }

        return [
            'user_id' => User::factory(),
            'old_username' => $oldUsername,
            'new_username' => $newUsername,
            'changed_by' => fake()->boolean(70) ? User::factory() : null,
            'reason' => fake()->optional(0.2)->sentence(),
            'changed_at' => fake()->dateTimeBetween('-90 days', 'now'),
            'ip_address' => fake()->optional(0.6)->ipv4(),
            'user_agent' => fake()->optional(0.4)->userAgent(),
        ];
    }
}
