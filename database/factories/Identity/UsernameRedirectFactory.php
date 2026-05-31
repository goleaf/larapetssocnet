<?php

namespace Database\Factories\Identity;

use App\Models\Identity\User;
use App\Models\Identity\UsernameRedirect;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<UsernameRedirect>
 */
class UsernameRedirectFactory extends Factory
{
    protected $model = UsernameRedirect::class;

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

        return [
            'old_username' => $oldUsername,
            'user_id' => User::factory(),
            'redirects_until' => fake()->dateTimeBetween('+1 day', '+14 days'),
        ];
    }
}
