<?php

namespace Database\Factories;

use App\Models\Group;
use App\Models\GroupJoinRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GroupJoinRequest>
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
