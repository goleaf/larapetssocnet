<?php

namespace Database\Factories\Activities;

use App\Models\Activities\Event;
use App\Models\Activities\EventAttendee;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventAttendee>
 */
class EventAttendeeFactory extends Factory
{
    protected $model = EventAttendee::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'user_id' => User::factory(),
            'status' => fake()->randomElement([
                Event::ATTENDEE_GOING,
                Event::ATTENDEE_INTERESTED,
                Event::ATTENDEE_DECLINED,
            ]),
            'responded_at' => fake()->optional(0.75)->dateTimeBetween('-2 days', 'now'),
        ];
    }
}
