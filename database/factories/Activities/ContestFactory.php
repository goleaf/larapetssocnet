<?php

namespace Database\Factories\Activities;

use App\Models\Activities\Contest;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contest>
 */
class ContestFactory extends Factory
{
    protected $model = Contest::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->sentence(fake()->numberBetween(3, 7));
        $startsAt = now()->addDays(fake()->numberBetween(1, 60));

        return [
            'organizer_user_id' => User::factory(),
            'title' => $title,
            'slug' => fake()->unique()->slug(3),
            'description' => fake()->optional(0.9)->paragraph(),
            'prize' => fake()->optional(0.7)->randomElement(['$25 gift card', 'custom medal', 'pet care bundle', 'training session']),
            'species' => fake()->optional(0.5)->randomElement(['dog', 'cat', 'rabbit', 'bird']),
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addDays(fake()->numberBetween(7, 21)),
            'max_entries' => fake()->numberBetween(10, 250),
            'entries_count' => 0,
            'winner_entry_id' => null,
            'status' => fake()->randomElement(Contest::STATUSES),
        ];
    }
}
