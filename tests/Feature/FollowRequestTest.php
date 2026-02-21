<?php

namespace Tests\Feature;

use App\Models\Follow;
use App\Models\User;
use App\Notifications\FollowRequestApproved;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class FollowRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_approve_pending_follow_request(): void
    {
        Notification::fake();

        $owner = User::factory()->create(['is_private' => true]);
        $requester = User::factory()->create();

        Follow::query()->create([
            'follower_id' => $requester->id,
            'following_id' => $owner->id,
            'status' => 'pending',
            'created_at' => now(),
        ]);

        $this->actingAs($owner)
            ->postJson(route('follow-requests.approve', ['user' => $requester->username]))
            ->assertOk();

        $this->assertDatabaseHas('follows', [
            'follower_id' => $requester->id,
            'following_id' => $owner->id,
            'status' => 'accepted',
        ]);

        Notification::assertSentTo($requester, FollowRequestApproved::class);
    }

    public function test_owner_can_reject_pending_follow_request(): void
    {
        $owner = User::factory()->create(['is_private' => true]);
        $requester = User::factory()->create();

        Follow::query()->create([
            'follower_id' => $requester->id,
            'following_id' => $owner->id,
            'status' => 'pending',
            'created_at' => now(),
        ]);

        $this->actingAs($owner)
            ->postJson(route('follow-requests.reject', ['user' => $requester->username]))
            ->assertOk();

        $this->assertDatabaseMissing('follows', [
            'follower_id' => $requester->id,
            'following_id' => $owner->id,
            'status' => 'pending',
        ]);
    }
}
