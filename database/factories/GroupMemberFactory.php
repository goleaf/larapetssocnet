<?php

namespace Database\Factories;

use App\Enums\GroupMemberRole;
use App\Enums\GroupMemberStatus;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GroupMember>
 */
class GroupMemberFactory extends Factory
{
    protected $model = GroupMember::class;

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
            'role' => GroupMemberRole::Member->value,
            'status' => GroupMemberStatus::Active->value,
            'joined_at' => now(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (): array => [
            'status' => GroupMemberStatus::Pending->value,
            'joined_at' => null,
        ]);
    }

    public function banned(): static
    {
        return $this->state(fn (): array => [
            'status' => GroupMemberStatus::Banned->value,
            'joined_at' => null,
        ]);
    }
}
