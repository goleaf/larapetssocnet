<?php

namespace Database\Factories;

use App\Enums\GroupInvitationStatus;
use App\Enums\GroupMemberRole;
use App\Models\Groups\Group;
use App\Models\Groups\GroupInvitation;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GroupInvitation>
 */
class GroupInvitationFactory extends Factory
{
    protected $model = GroupInvitation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group_id' => Group::factory(),
            'invited_user_id' => User::factory(),
            'invited_by_user_id' => User::factory(),
            'status' => GroupInvitationStatus::Pending->value,
            'role' => GroupMemberRole::Member->value,
            'message' => fake()->optional(0.35)->sentence(),
            'responded_at' => null,
            'expires_at' => now()->addDays(14),
        ];
    }

    public function accepted(): static
    {
        return $this->state(fn (): array => [
            'status' => GroupInvitationStatus::Accepted->value,
            'responded_at' => now(),
        ]);
    }

    public function declined(): static
    {
        return $this->state(fn (): array => [
            'status' => GroupInvitationStatus::Declined->value,
            'responded_at' => now(),
        ]);
    }
}
