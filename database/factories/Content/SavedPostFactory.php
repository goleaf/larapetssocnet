<?php

namespace Database\Factories\Content;

use App\Models\Content\Post;
use App\Models\Content\SavedPost;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SavedPost>
 */
class SavedPostFactory extends Factory
{
    protected $model = SavedPost::class;

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
        ];
    }
}
