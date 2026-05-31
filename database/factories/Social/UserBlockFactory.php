<?php

namespace Database\Factories\Social;

use App\Models\Identity\User;
use App\Models\Social\UserBlock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserBlock>
 */
class UserBlockFactory extends Factory
{
    protected $model = UserBlock::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'blocker_id' => User::factory(),
            'blocked_id' => User::factory(),
        ];
    }
}
