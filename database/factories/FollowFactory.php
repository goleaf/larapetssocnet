<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Identity\User;
use App\Models\Social\Follow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Follow>
 */
class FollowFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'follower_id' => User::factory(),
            'following_id' => User::factory(),
            'status' => $this->faker->randomElement(['accepted', 'pending']),
            'created_at' => now(),
        ];
    }
}
