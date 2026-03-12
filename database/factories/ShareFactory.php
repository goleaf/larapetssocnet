<?php

namespace Database\Factories;

use App\Models\Post;
use App\Models\Share;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Share>
 */
class ShareFactory extends Factory
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
            'shareable_type' => Post::class,
            'shareable_id' => Post::factory(),
            'method' => 'copy_link',
        ];
    }
}
