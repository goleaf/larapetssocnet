<?php

namespace Database\Factories\Pets;

use App\Models\Identity\User;
use App\Models\Pets\Pet;
use App\Models\Pets\PetOwnershipTransfer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PetOwnershipTransfer>
 */
class PetOwnershipTransferFactory extends Factory
{
    protected $model = PetOwnershipTransfer::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pet_id' => Pet::factory(),
            'current_owner_user_id' => User::factory(),
            'proposed_owner_user_id' => User::factory(),
            'status' => PetOwnershipTransfer::STATUS_PENDING,
            'expires_at' => now()->addDays(fake()->numberBetween(7, 30)),
        ];
    }
}
