<?php

namespace Database\Factories\Social;

use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Models\Social\FeedItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeedItem>
 */
class FeedItemFactory extends Factory
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
            'source_type' => FeedItem::SOURCE_USER,
            'source_id' => User::factory(),
            'post_created_at' => now(),
        ];
    }
}
