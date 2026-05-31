<?php

namespace Database\Factories\Activities;

use App\Models\Activities\Contest;
use App\Models\Activities\ContestEntry;
use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Models\Pets\Pet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContestEntry>
 */
class ContestEntryFactory extends Factory
{
    protected $model = ContestEntry::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $caption = fake()->optional(0.75)->sentences(2, true);

        return [
            'contest_id' => Contest::factory(),
            'user_id' => User::factory(),
            'pet_id' => Pet::factory(),
            'post_id' => Post::factory(),
            'caption' => $caption,
            'votes_count' => 0,
            'is_winner' => false,
        ];
    }
}
