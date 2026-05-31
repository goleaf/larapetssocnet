<?php

namespace Database\Factories\Pets;

use App\Enums\Pets\PetWeightUnit;
use App\Models\Pets\Pet;
use App\Models\Pets\PetWeightEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PetWeightEntry>
 */
class PetWeightEntryFactory extends Factory
{
    protected $model = PetWeightEntry::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pet_id' => Pet::factory(),
            'entry_date' => fake()->date(),
            'weight_value' => fake()->randomFloat(2, 1, 75),
            'weight_unit' => fake()->randomElement(PetWeightUnit::values()),
            'note' => fake()->optional(0.5)->sentence(),
        ];
    }
}
