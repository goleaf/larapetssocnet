<?php

namespace Database\Factories\Pets;

use App\Models\Pets\Species;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Species>
 */
class SpeciesFactory extends Factory
{
    protected $model = Species::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('##'),
            'icon_identifier' => fake()->optional(0.6)->word(),
            'color_identifier' => fake()->optional(0.5)->safeColorName(),
            'gradient_from' => fake()->optional(0.5)->safeColorName(),
            'gradient_to' => fake()->optional(0.5)->safeColorName(),
            'display_order' => 0,
            'life_stage_config' => null,
        ];
    }
}
