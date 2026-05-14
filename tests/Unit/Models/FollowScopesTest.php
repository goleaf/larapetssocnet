<?php

use App\Models\Follow;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('filters follow rows by followers scope', function (): void {
    $targetUser = User::factory()->create();
    $follower = User::factory()->create();
    $otherTarget = User::factory()->create();

    $matchingFollow = Follow::factory()->create([
        'follower_id' => $follower->getKey(),
        'following_id' => $targetUser->getKey(),
    ]);

    $otherFollow = Follow::factory()->create([
        'follower_id' => $follower->getKey(),
        'following_id' => $otherTarget->getKey(),
    ]);

    $followIds = Follow::query()
        ->followers($targetUser->getKey())
        ->pluck('follows.id');

    expect($followIds)
        ->toContain($matchingFollow->getKey())
        ->not->toContain($otherFollow->getKey());
});

it('filters follow rows by following scope', function (): void {
    $sourceUser = User::factory()->create();
    $followed = User::factory()->create();
    $otherFollower = User::factory()->create();

    $matchingFollow = Follow::factory()->create([
        'follower_id' => $sourceUser->getKey(),
        'following_id' => $followed->getKey(),
    ]);

    $otherFollow = Follow::factory()->create([
        'follower_id' => $otherFollower->getKey(),
        'following_id' => $followed->getKey(),
    ]);

    $followIds = Follow::query()
        ->following($sourceUser->getKey())
        ->pluck('follows.id');

    expect($followIds)
        ->toContain($matchingFollow->getKey())
        ->not->toContain($otherFollow->getKey());
});
