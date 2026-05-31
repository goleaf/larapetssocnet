<?php

namespace Database\Factories\Pets;

use App\Models\Identity\User;
use App\Models\Pets\PhotoGallery;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PhotoGallery>
 */
class PhotoGalleryFactory extends Factory
{
    protected $model = PhotoGallery::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->optional(0.6)->paragraph(),
            'cover_media_id' => null,
        ];
    }
}
