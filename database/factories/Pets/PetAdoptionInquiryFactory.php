<?php

namespace Database\Factories\Pets;

use App\Models\Identity\User;
use App\Models\Marketplace\MarketplaceListing;
use App\Models\Pets\Pet;
use App\Models\Pets\PetAdoptionInquiry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PetAdoptionInquiry>
 */
class PetAdoptionInquiryFactory extends Factory
{
    protected $model = PetAdoptionInquiry::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pet_id' => Pet::factory(),
            'marketplace_listing_id' => fake()->optional(0.4)->randomElement([null, MarketplaceListing::factory()]),
            'user_id' => fake()->optional(0.8)->randomElement([null, User::factory()]),
            'applicant_name' => fake()->name(),
            'city' => fake()->optional(0.6)->city(),
            'country' => fake()->optional(0.6)->country(),
            'living_situation' => fake()->randomElement(['alone', 'with_family', 'with_roommate', 'other_owner']),
            'species_experience' => fake()->randomElement(['none', 'beginner', 'intermediate', 'experienced']),
            'other_pets' => fake()->optional(0.5)->sentence(),
            'message' => fake()->sentence(),
            'preferred_contact_method' => fake()->randomElement(['email', 'phone', 'in_app']),
            'contact_details' => fake()->randomElement([fake()->safeEmail(), fake()->phoneNumber(), fake()->address()]),
            'status' => PetAdoptionInquiry::STATUS_SENT,
        ];
    }
}
