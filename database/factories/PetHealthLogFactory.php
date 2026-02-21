<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PetHealthLog>
 */
class PetHealthLogFactory extends Factory
{
    protected $model = \App\Models\PetHealthLog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pet_id' => \App\Models\Pet::factory(),
            'logged_by_user_id' => \App\Models\User::factory(),
            'log_type' => fake()->randomElement(['checkup', 'vaccine', 'medication', 'weight']),
            'title' => fake()->optional(0.8)->sentence(fake()->numberBetween(2, 5)),
            'notes' => fake()->optional(0.8)->paragraph(),
            'weight_kg' => fake()->optional(0.7)->randomFloat(2, 1, 60),
            'temperature_c' => fake()->optional(0.3)->randomFloat(1, 36.0, 40.0),
            'logged_at' => fake()->dateTimeBetween('-180 days', 'now'),
        ];
    }
}
