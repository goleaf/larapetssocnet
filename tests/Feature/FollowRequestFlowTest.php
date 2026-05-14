<?php

use App\Models\Follow;
use App\Models\User;
use App\Services\FollowService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows a requester to cancel a pending follow request', function (): void {
    $requester = User::factory()->create();
    $target = User::factory()->create(['is_private' => true]);

    $this->actingAs($requester)
        ->postJson(route('users.follow', ['user' => $target->username]))
        ->assertSuccessful()
        ->assertJsonPath('follow_status', 'pending');

    $this->assertDatabaseHas('follows', [
        'follower_id' => $requester->id,
        'following_id' => $target->id,
        'status' => 'pending',
    ]);

    expect($target->fresh()->follow_requests_count)->toBe(1);

    $this->actingAs($requester)
        ->deleteJson(route('users.unfollow', ['user' => $target->username]))
        ->assertSuccessful()
        ->assertJsonPath('follow_status', 'none');

    $this->assertDatabaseMissing('follows', [
        'follower_id' => $requester->id,
        'following_id' => $target->id,
    ]);

    expect($target->fresh()->follow_requests_count)->toBe(0);
});

it('allows an owner to remove an accepted follower', function (): void {
    $owner = User::factory()->create(['is_private' => false]);
    $follower = User::factory()->create();

    app(FollowService::class)->follow($follower, $owner);

    $this->actingAs($owner)
        ->deleteJson(route('users.remove-follower', ['user' => $follower->username]))
        ->assertSuccessful();

    $this->assertDatabaseMissing('follows', [
        'follower_id' => $follower->id,
        'following_id' => $owner->id,
        'status' => 'accepted',
    ]);

    expect($owner->fresh()->followers_count)->toBe(0)
        ->and($follower->fresh()->following_count)->toBe(0);
});

it('does not grant profile access for pending requests', function (): void {
    $owner = User::factory()->create(['is_private' => true]);
    $requester = User::factory()->create();

    Follow::query()->create([
        'follower_id' => $requester->id,
        'following_id' => $owner->id,
        'status' => 'pending',
        'created_at' => now(),
    ]);

    expect($owner->canViewProfile($requester))->toBeFalse();
});
