<?php

namespace Database\Factories\Pets;

use App\Models\Pets\Pet;
use App\Models\Pets\PetHealthReminder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PetHealthReminder>
 */
class PetHealthReminderFactory extends Factory
{
    protected $model = PetHealthReminder::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pet_id' => Pet::factory(),
            'reminder_type' => fake()->randomElement(PetHealthReminder::types()),
            'frequency_days' => fake()->numberBetween(1, 30),
            'last_sent_on' => fake()->optional(0.6)->date(),
            'next_due_on' => fake()->dateTimeBetween('+1 day', '+60 days')->format('Y-m-d'),
            'custom_text' => fake()->optional(0.2)->sentence(),
        ];
    }
}
