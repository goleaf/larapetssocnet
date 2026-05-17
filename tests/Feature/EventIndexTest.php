<?php

namespace Tests\Feature;

use App\Models\Activities\Event;
use App\Models\Identity\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EventIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_events_index_renders_without_location_property_errors(): void
    {
        $creator = User::factory()->create();

        Event::factory()->create([
            'creator_user_id' => $creator->id,
            'location_text' => null,
            'status' => 'scheduled',
        ]);

        Event::factory()->create([
            'creator_user_id' => $creator->id,
            'location_text' => 'Austin',
            'status' => 'scheduled',
        ]);

        $this->actingAs($creator)
            ->get(route('events.index'))
            ->assertSuccessful();
    }

    public function test_user_can_toggle_going_rsvp_for_event(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create([
            'status' => 'scheduled',
            'attendees_count' => 0,
        ]);

        $this->actingAs($user)
            ->post(route('events.rsvp', $event->getKey()), [
                'status' => 'going',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('event_attendees', [
            'event_id' => $event->getKey(),
            'user_id' => $user->getKey(),
            'status' => 'going',
        ]);

        $event->refresh();

        if (Schema::hasColumn('events', 'attendees_count')) {
            $this->assertSame(1, (int) $event->attendees_count);
        }

        $this->actingAs($user)
            ->post(route('events.rsvp', $event->getKey()), [
                'status' => 'going',
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('event_attendees', [
            'event_id' => $event->getKey(),
            'user_id' => $user->getKey(),
        ]);

        $event->refresh();

        if (Schema::hasColumn('events', 'attendees_count')) {
            $this->assertSame(0, (int) $event->attendees_count);
        }
    }
}
