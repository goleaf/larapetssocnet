<?php

namespace Tests\Unit;

use App\Models\Badge;
use App\Models\User;
use App\Notifications\BadgeAwarded;
use App\Services\BadgeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class BadgeServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_award_attaches_badge_and_sends_notification(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $badge = Badge::query()->create([
            'name' => 'Integration Tester',
            'slug' => 'integration_tester',
            'description' => 'Awarded for integration test coverage.',
            'icon' => 'TEST',
            'color' => 'emerald',
            'type' => 'manual',
            'condition_type' => 'manual',
            'condition_value' => 0,
        ]);

        app(BadgeService::class)->award($user, $badge->slug);

        $this->assertDatabaseHas('user_badges', [
            'user_id' => $user->id,
            'badge_id' => $badge->id,
        ]);

        Notification::assertSentTo($user, BadgeAwarded::class, function (BadgeAwarded $notification): bool {
            return ! $notification->badge->relationLoaded('users');
        });
    }
}
