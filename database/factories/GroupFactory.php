<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Group>
 */
class GroupFactory extends Factory
{
    protected $model = \App\Models\Group::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(fake()->numberBetween(2, 4), true);

        return [
            'owner_user_id' => \App\Models\User::factory(),
            'name' => Str::title($name),
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1000, 9999),
            'description' => fake()->optional()->sentence(),
            'privacy' => fake()->randomElement(['public', 'private']),
            'cover_image_path' => fake()->optional(0.35)->imageUrl(1200, 600, 'nature', true),
            'members_count' => 0,
            'posts_count' => 0,
        ];
    }
}
