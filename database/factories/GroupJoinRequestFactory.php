<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Groups\Group;
use App\Models\Groups\GroupJoinRequest;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GroupJoinRequest>
 */
class GroupJoinRequestFactory extends Factory
{
    protected $model = GroupJoinRequest::class;

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
            'status' => 'pending',
            'reviewed_by' => null,
            'reviewed_at' => null,
            'message' => fake()->optional(0.4)->sentence(),
        ];
    }
}
