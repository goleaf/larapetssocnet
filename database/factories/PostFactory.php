<?php

namespace Database\Factories;

use App\Models\Content\Post;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Model>
     */
    protected $model = Post::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $body = $this->faker->paragraphs(3, true);

        return [
            'user_id' => User::factory(),
            'pet_id' => null,
            'body' => $body,
            'body_html' => '<p>'.e($body).'</p>',
            'type' => 'text',
            'status' => 'published',
            'published_at' => now(),
            'visibility' => Post::VISIBILITY_PUBLIC,
            'location' => $this->faker->optional(0.3)->city(),
            'is_pinned' => false,
            'pinned_at' => null,
            'edited_at' => null,
            'likes_count' => 0,
            'comments_count' => 0,
            'reactions_count' => 0,
            'shares_count' => 0,
            'save_count' => 0,
            'metadata' => null,
        ];
    }
}
