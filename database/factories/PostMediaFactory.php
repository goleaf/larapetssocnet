<?php

namespace Database\Factories;

use App\Models\Post;
use App\Models\PostMedia;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PostMedia>
 */
class PostMediaFactory extends Factory
{
    protected $model = PostMedia::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'post_id' => Post::factory(),
            'file_path' => 'posts/'.$this->faker->uuid().'.jpg',
            'media_type' => $this->faker->randomElement(['image', 'video']),
            'order' => $this->faker->numberBetween(0, 5),
        ];
    }
}
