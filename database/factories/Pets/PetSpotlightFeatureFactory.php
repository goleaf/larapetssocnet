<?php

namespace Database\Factories\Pets;

use App\Models\Pets\Pet;
use App\Models\Pets\PetSpotlightFeature;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PetSpotlightFeature>
 */
class PetSpotlightFeatureFactory extends Factory
{
    protected $model = PetSpotlightFeature::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $weekStart = now()->startOfWeek();

        return [
            'pet_id' => Pet::factory(),
            'featured_week_start' => $weekStart->toDateString(),
            'engagement_rate' => fake()->randomFloat(4, 0, 100),
            'selected_at' => $weekStart,
            'expires_at' => $weekStart->copy()->addDays(7),
        ];
    }
}
