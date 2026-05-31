<?php

namespace Database\Factories\Pets;

use App\Models\Pets\Pet;
use App\Models\Pets\PetRelationship;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PetRelationship>
 */
class PetRelationshipFactory extends Factory
{
    protected $model = PetRelationship::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source_pet_id' => Pet::factory(),
            'target_pet_id' => Pet::factory(),
            'relationship_type' => fake()->randomElement([
                PetRelationship::TYPE_PARENT,
                PetRelationship::TYPE_OFFSPRING,
                PetRelationship::TYPE_SIBLING,
                PetRelationship::TYPE_MATE,
            ]),
            'note' => fake()->optional(0.4)->sentence(),
        ];
    }
}
