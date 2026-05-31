<?php

namespace Database\Factories\Marketplace;

use App\Models\Marketplace\Listing;
use App\Models\Marketplace\ListingImage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ListingImage>
 */
class ListingImageFactory extends Factory
{
    protected $model = ListingImage::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'listing_id' => Listing::factory(),
            'file_path' => 'listing-images/'.fake()->uuid().'.jpg',
            'order' => fake()->numberBetween(0, 10),
            'is_cover' => fake()->boolean(),
        ];
    }
}
