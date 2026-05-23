<?php

declare(strict_types=1);

namespace Database\Factories\Pets;

use App\Models\Identity\User;
use App\Models\Pets\Pet;
use App\Models\Pets\PetOwner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PetOwner>
 */
class PetOwnerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pet_id' => Pet::factory(),
            'user_id' => User::factory(),
            'invited_by_user_id' => User::factory(),
            'role' => PetOwner::ROLE_CO_OWNER,
            'can_post' => true,
            'can_edit' => false,
            'can_manage_health' => false,
            'can_manage_gallery' => false,
            'can_manage_adoption' => false,
            'can_delete' => false,
            'accepted_at' => now(),
        ];
    }
}
