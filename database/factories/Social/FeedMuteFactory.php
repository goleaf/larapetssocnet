<?php

namespace Database\Factories\Social;

use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Models\Social\FeedMute;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeedMute>
 */
class FeedMuteFactory extends Factory
{
    protected $model = FeedMute::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'mutable_type' => Post::class,
            'mutable_id' => Post::factory(),
        ];
    }
}
