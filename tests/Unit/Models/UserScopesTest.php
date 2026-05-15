<?php

use App\Models\Identity\User;
use App\Models\Social\Follow;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('filters active users', function (): void {
    $activeUser = User::factory()->create([
        'is_banned' => false,
        'scheduled_deletion_at' => null,
    ]);

    $bannedUser = User::factory()->create(['is_banned' => true]);
    $scheduledForDeletionUser = User::factory()->create(['scheduled_deletion_at' => now()->addDay()]);

    $userIds = User::query()
        ->active()
        ->pluck('users.id');

    expect($userIds)
        ->toContain($activeUser->getKey())
        ->not->toContain($bannedUser->getKey())
        ->not->toContain($scheduledForDeletionUser->getKey());
});

it('filters users with public profile visibility', function (): void {
    $publicUser = User::factory()->create([
        'profile_visibility' => 'public',
        'is_private' => false,
        'is_banned' => false,
    ]);

    $privateUser = User::factory()->create([
        'profile_visibility' => 'public',
        'is_private' => true,
    ]);

    $bannedUser = User::factory()->create([
        'profile_visibility' => 'public',
        'is_private' => false,
        'is_banned' => true,
    ]);

    $followersOnlyUser = User::factory()->create([
        'profile_visibility' => 'followers_only',
        'is_private' => false,
        'is_banned' => false,
    ]);

    $userIds = User::query()
        ->withPublicProfile()
        ->pluck('users.id');

    expect($userIds)
        ->toContain($publicUser->getKey())
        ->not->toContain($privateUser->getKey())
        ->not->toContain($bannedUser->getKey())
        ->not->toContain($followersOnlyUser->getKey());
});

it('filters users followed by a specific user', function (): void {
    $viewer = User::factory()->create();
    $acceptedFollowing = User::factory()->create();
    $pendingFollowing = User::factory()->create();

    Follow::factory()->create([
        'follower_id' => $viewer->getKey(),
        'following_id' => $acceptedFollowing->getKey(),
        'status' => 'accepted',
    ]);

    Follow::factory()->create([
        'follower_id' => $viewer->getKey(),
        'following_id' => $pendingFollowing->getKey(),
        'status' => 'pending',
    ]);

    $userIds = User::query()
        ->followedBy($viewer->getKey())
        ->pluck('users.id');

    expect($userIds)
        ->toContain($acceptedFollowing->getKey())
        ->not->toContain($pendingFollowing->getKey());
});
