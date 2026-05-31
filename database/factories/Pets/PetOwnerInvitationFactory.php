<?php

namespace Database\Factories\Pets;

use App\Enums\Pets\PetOwnerRole;
use App\Models\Identity\User;
use App\Models\Pets\Pet;
use App\Models\Pets\PetOwnerInvitation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PetOwnerInvitation>
 */
class PetOwnerInvitationFactory extends Factory
{
    protected $model = PetOwnerInvitation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pet_id' => Pet::factory(),
            'invited_user_id' => User::factory(),
            'inviting_user_id' => User::factory(),
            'role' => fake()->randomElement(PetOwnerRole::values()),
            'status' => PetOwnerInvitation::STATUS_PENDING,
            'expires_at' => now()->addDays(fake()->numberBetween(5, 60)),
            'responded_at' => null,
        ];
    }
}
