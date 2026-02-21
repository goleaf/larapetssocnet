<?php

namespace Database\Factories;

use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Post>
 */
class PostFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = Post::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'pet_id' => null,
            'body' => $this->faker->paragraphs(3, true),
            'body_html' => '<p>' . implode('</p><p>', $this->faker->paragraphs(3)) . '</p>',
            'type' => 'text',
            'visibility' => $this->faker->randomElement(['public', 'followers', 'private']),
            'location' => $this->faker->optional(0.3)->city(),
            'is_pinned' => false,
            'likes_count' => 0,
            'comments_count' => 0,
            'shares_count' => 0,
        ];
    }
}
