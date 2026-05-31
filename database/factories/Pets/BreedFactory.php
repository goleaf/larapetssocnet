<?php

namespace Database\Factories\Pets;

use App\Models\Pets\Breed;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Breed>
 */
class BreedFactory extends Factory
{
    protected $model = Breed::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->words(2, true);

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('###'),
            'species_slug' => fake()->optional(0.5)->randomElement(['dog', 'cat', 'rabbit', 'bird']),
            'species_id' => null,
            'normalized_name' => Str::slug($name),
        ];
    }
}
