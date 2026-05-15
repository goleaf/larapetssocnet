<?php

use App\Enums\FollowAbility;
use App\Models\Identity\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

it('registers follow abilities for enum-based gate checks', function (): void {
    expect(Gate::has(FollowAbility::Follow))->toBeTrue()
        ->and(Gate::has(FollowAbility::Unfollow))->toBeTrue()
        ->and(Gate::has(FollowAbility::ViewFollowers))->toBeTrue()
        ->and(Gate::has(FollowAbility::ViewFollowing))->toBeTrue();
});

it('authorizes follow actions using enum abilities', function (): void {
    $actor = User::factory()->create();
    $target = User::factory()->create();

    expect(Gate::forUser($actor)->allows(FollowAbility::Follow, $target))->toBeTrue()
        ->and(Gate::forUser($actor)->allows(FollowAbility::Unfollow, $target))->toBeTrue()
        ->and(Gate::forUser($actor)->allows(FollowAbility::ViewFollowers, $target))->toBeTrue()
        ->and(Gate::forUser($actor)->allows(FollowAbility::ViewFollowing, $target))->toBeTrue();
});
