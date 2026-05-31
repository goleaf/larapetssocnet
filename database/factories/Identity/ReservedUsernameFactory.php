<?php

namespace Database\Factories\Identity;

use App\Models\Identity\ReservedUsername;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ReservedUsername>
 */
class ReservedUsernameFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $username = (string) Str::of(fake()->unique()->userName())
            ->lower()
            ->replaceMatches('/[^a-z0-9_]/', '')
            ->trim('_')
            ->limit(30, '');

        if ($username === '') {
            $username = 'reserved_'.fake()->unique()->numerify('####');
        }

        return [
            'username' => $username,
            'reason' => fake()->optional(0.4)->sentence(),
        ];
    }
}
