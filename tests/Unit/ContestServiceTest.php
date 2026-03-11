<?php

namespace Tests\Unit;

use App\Models\Contest;
use App\Models\ContestEntry;
use App\Models\User;
use App\Notifications\ContestWinner;
use App\Services\ContestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ContestServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_pick_winner_marks_entry_and_sends_notification(): void
    {
        Notification::fake();

        $organizer = User::factory()->create();
        $entrant = User::factory()->create([
            'notification_preferences' => ['contest_updates' => true],
        ]);

        $contest = Contest::query()->create([
            'organizer_user_id' => $organizer->id,
            'title' => 'Spring Pet Show',
            'slug' => 'spring-pet-show',
            'description' => 'Seasonal contest',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'status' => 'voting',
            'max_entries' => 100,
        ]);

        $entry = ContestEntry::query()->create([
            'contest_id' => $contest->id,
            'user_id' => $entrant->id,
            'caption' => 'My best pose',
        ]);

        $contest->load('organizer', 'votes');
        $entry->load('user');

        app(ContestService::class)->pickWinner($contest, $entry, $organizer);

        $this->assertDatabaseHas('contests', [
            'id' => $contest->id,
            'winner_entry_id' => $entry->id,
            'status' => 'ended',
        ]);

        $this->assertDatabaseHas('contest_entries', [
            'id' => $entry->id,
            'is_winner' => 1,
        ]);

        Notification::assertSentTo($entrant, ContestWinner::class, function (ContestWinner $notification): bool {
            return ! $notification->contest->relationLoaded('organizer')
                && $notification->contest->relationLoaded('votes')
                && ! $notification->entry->relationLoaded('user');
        });
    }
}
