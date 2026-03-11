<?php

use App\Models\Follow;
use App\Models\User;
use App\Models\UserBlock;
use App\Services\FollowSuggestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('excludes followed, pending, blocked, private, and self from suggestions', function (): void {
    $viewer = User::factory()->create();

    $followed = User::factory()->create(['is_private' => false]);
    $pending = User::factory()->create(['is_private' => false]);
    $blocked = User::factory()->create(['is_private' => false]);
    $privateUser = User::factory()->create(['is_private' => true]);
    $candidate = User::factory()->create(['is_private' => false]);

    Follow::factory()->create([
        'follower_id' => $viewer->id,
        'following_id' => $followed->id,
        'status' => 'accepted',
    ]);

    Follow::factory()->create([
        'follower_id' => $viewer->id,
        'following_id' => $pending->id,
        'status' => 'pending',
    ]);

    UserBlock::query()->create([
        'blocker_id' => $viewer->id,
        'blocked_id' => $blocked->id,
    ]);

    $suggestions = app(FollowSuggestionService::class)->forUser($viewer, 10);
    $suggestedIds = $suggestions->modelKeys();

    expect($suggestedIds)
        ->not->toContain($viewer->id)
        ->not->toContain($followed->id)
        ->not->toContain($pending->id)
        ->not->toContain($blocked->id)
        ->not->toContain($privateUser->id)
        ->toContain($candidate->id);
});
