<?php

namespace Database\Factories\Content;

use App\Models\Content\Post;
use App\Models\Content\PostReport;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PostReport>
 */
class PostReportFactory extends Factory
{
    protected $model = PostReport::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'post_id' => Post::factory(),
            'user_id' => User::factory(),
            'reason' => fake()->randomElement(['spam', 'harassment', 'misinformation', 'other']),
            'details' => fake()->optional(0.8)->sentence(),
        ];
    }
}
