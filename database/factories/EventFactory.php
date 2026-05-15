<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Activities\Event;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    protected $model = Event::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startAt = fake()->dateTimeBetween('-10 days', '+30 days');

        return [
            'group_id' => null,
            'creator_user_id' => User::factory(),
            'title' => fake()->sentence(fake()->numberBetween(2, 6)),
            'description' => fake()->optional()->paragraph(),
            'location_text' => fake()->optional(0.85)->city(),
            'start_at' => $startAt,
            'end_at' => fake()->optional(0.8)->dateTimeBetween($startAt, '+60 days'),
            'status' => fake()->randomElement(['scheduled', 'completed', 'cancelled']),
            'attendees_count' => 0,
        ];
    }
}
