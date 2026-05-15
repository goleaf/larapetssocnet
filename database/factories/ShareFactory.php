<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Content\Post;
use App\Models\Content\Share;
use App\Models\Identity\User;
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
            'shareable_type' => (new Post)->getMorphClass(),
            'shareable_id' => Post::factory(),
            'method' => 'copy_link',
        ];
    }
}
