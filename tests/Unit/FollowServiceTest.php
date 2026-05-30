<?php

namespace Tests\Unit;

use App\Models\Identity\User;
use App\Notifications\Database\Social\NewFollower;
use App\Services\FollowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class FollowServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_follow_returns_pending_for_private_target(): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->create(['is_private' => true]);

        $status = app(FollowService::class)->follow($actor, $target);

        $this->assertSame('pending', $status);
    }

    public function test_unfollow_is_idempotent(): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->create();

        app(FollowService::class)->unfollow($actor, $target);

        $this->assertDatabaseMissing('follows', [
            'follower_id' => $actor->getKey(),
            'following_id' => $target->getKey(),
        ]);
        $this->assertSame(0, $actor->refresh()->following_count);
        $this->assertSame(0, $target->refresh()->followers_count);
    }

    public function test_follow_notification_uses_relation_light_actor_model(): void
    {
        Notification::fake();

        $actor = User::factory()->create();
        $target = User::factory()->create();

        $actor->load('media', 'followers', 'following', 'acceptedFollowers', 'acceptedFollowing');

        $status = app(FollowService::class)->follow($actor, $target);

        $this->assertSame('following', $status);
        $this->assertTrue($actor->relationLoaded('media'));
        $this->assertTrue($actor->relationLoaded('followers'));
        $this->assertTrue($actor->relationLoaded('following'));
        $this->assertTrue($actor->relationLoaded('acceptedFollowers'));
        $this->assertTrue($actor->relationLoaded('acceptedFollowing'));

        Notification::assertSentTo($target, NewFollower::class, function (NewFollower $notification): bool {
            return ! $notification->follower->relationLoaded('followers')
                && ! $notification->follower->relationLoaded('following')
                && ! $notification->follower->relationLoaded('acceptedFollowers')
                && ! $notification->follower->relationLoaded('acceptedFollowing')
                && $notification->follower->relationLoaded('media');
        });
    }
}
