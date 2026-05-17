<?php

namespace Tests\Feature;

use App\Models\Identity\User;
use App\Notifications\NewFollower;
use App\Notifications\NewFollowRequest;
use DB;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class FollowTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_follow_and_unfollow_public_user(): void
    {
        Notification::fake();

        $actor = User::factory()->create(['is_private' => false]);
        $target = User::factory()->create(['is_private' => false]);

        $this->actingAs($actor)
            ->postJson(route('users.follow', ['user' => $target->username]))
            ->assertOk()
            ->assertJsonPath('follow_status', 'following');

        $this->assertDatabaseHas('follows', [
            'follower_id' => $actor->id,
            'following_id' => $target->id,
            'status' => 'accepted',
        ]);

        Notification::assertSentTo($target, NewFollower::class);

        $this->actingAs($actor)
            ->postJson(route('users.unfollow', ['user' => $target->username]))
            ->assertOk()
            ->assertJsonPath('follow_status', 'none');

        $this->assertDatabaseMissing('follows', [
            'follower_id' => $actor->id,
            'following_id' => $target->id,
        ]);
    }

    public function test_following_private_user_creates_pending_request_and_notification(): void
    {
        Notification::fake();

        $actor = User::factory()->create();
        $target = User::factory()->create(['is_private' => true]);

        $this->actingAs($actor)
            ->postJson(route('users.follow', ['user' => $target->username]))
            ->assertOk()
            ->assertJsonPath('follow_status', 'pending');

        $this->assertDatabaseHas('follows', [
            'follower_id' => $actor->id,
            'following_id' => $target->id,
            'status' => 'pending',
        ]);

        Notification::assertSentTo($target, NewFollowRequest::class);
    }

    public function test_follow_is_idempotent_and_does_not_duplicate_rows(): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->create();

        $this->actingAs($actor)->postJson(route('users.follow', ['user' => $target->username]))->assertOk();
        $this->actingAs($actor)->postJson(route('users.follow', ['user' => $target->username]))->assertOk();

        $this->assertSame(1, (int) DB::table('follows')
            ->where('follower_id', $actor->id)
            ->where('following_id', $target->id)
            ->count());
    }

    public function test_user_cannot_follow_self(): void
    {
        $actor = User::factory()->create();

        $this->actingAs($actor)
            ->postJson(route('users.follow', ['user' => $actor->username]))
            ->assertForbidden();
    }
}
