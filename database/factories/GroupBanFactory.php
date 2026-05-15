<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Groups\Group;
use App\Models\Groups\GroupBan;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GroupBan>
 */
class GroupBanFactory extends Factory
{
    protected $model = GroupBan::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group_id' => Group::factory(),
            'user_id' => User::factory(),
            'banned_by' => User::factory(),
            'reason' => fake()->optional()->sentence(),
            'created_at' => now(),
        ];
    }
}
