<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $this->get(route('events.index'))
            ->assertSuccessful();
    }
}
