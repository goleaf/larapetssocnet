<?php

namespace Database\Factories\Gamification;

use App\Models\Gamification\Badge;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Badge>
 */
class BadgeFactory extends Factory
{
    protected $model = Badge::class;

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
            'description' => fake()->optional(0.8)->sentence(),
            'icon' => fake()->emoji(),
            'condition_type' => 'manual',
            'condition_value' => fake()->numberBetween(0, 100),
        ];
    }
}
