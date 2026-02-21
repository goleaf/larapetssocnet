<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MarketplaceListing>
 */
class MarketplaceListingFactory extends Factory
{
    protected $model = \App\Models\MarketplaceListing::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => \App\Models\User::factory(),
            'pet_id' => null,
            'title' => fake()->sentence(fake()->numberBetween(3, 8)),
            'description' => fake()->paragraph(fake()->numberBetween(2, 5)),
            'price' => fake()->optional(0.7)->randomFloat(2, 20, 5000),
            'currency' => 'USD',
            'listing_type' => fake()->randomElement(['adoption', 'sale', 'service']),
            'status' => fake()->randomElement(['active', 'paused', 'closed']),
            'location_text' => fake()->optional(0.8)->city(),
            'contact_phone' => fake()->optional(0.5)->phoneNumber(),
            'contact_email' => fake()->optional(0.7)->safeEmail(),
            'views_count' => fake()->numberBetween(0, 500),
        ];
    }
}
