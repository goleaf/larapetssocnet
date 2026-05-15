<?php

namespace Database\Factories;

use App\Models\Content\Hashtag;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Hashtag>
 */
class HashtagFactory extends Factory
{
    protected $model = Hashtag::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = strtolower($this->faker->unique()->word());

        return [
            'name' => $name,
            'slug' => $name,
            'normalized_name' => $name,
            'posts_count' => $this->faker->numberBetween(0, 100),
        ];
    }
}
