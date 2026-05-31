<?php

namespace Database\Factories\Social;

use App\Models\Identity\User;
use App\Models\Social\UserFollow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserFollow>
 */
class UserFollowFactory extends Factory
{
    protected $model = UserFollow::class;

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
        ];
    }
}
