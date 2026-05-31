<?php

namespace Database\Factories\Pets;

use App\Models\Pets\Pet;
use App\Models\Pets\PetTag;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PetTag>
 */
class PetTagFactory extends Factory
{
    protected $model = PetTag::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->word();

        return [
            'pet_id' => Pet::factory(),
            'name' => $name,
            'slug' => $name.'-'.fake()->unique()->numerify('###'),
        ];
    }
}
