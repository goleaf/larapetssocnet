<?php

declare(strict_types=1);

namespace Database\Factories\Identity;

use App\Models\Content\Post;
use App\Models\Identity\ProfilePortfolioPost;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProfilePortfolioPost>
 */
class ProfilePortfolioPostFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'post_id' => Post::factory(),
            'display_order' => fake()->numberBetween(1, 12),
        ];
    }
}
