<?php

use App\Models\User;
use App\Services\ProfileVisibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows public profiles to be viewed by guests', function (): void {
    $owner = User::factory()->create([
        'profile_visibility' => 'public',
        'is_private' => false,
    ]);

    $service = app(ProfileVisibilityService::class);

    expect($service->canViewFullProfile(null, $owner))->toBeTrue();
});

it('allows accepted followers to view followers-only profiles', function (): void {
    $owner = User::factory()->create([
        'profile_visibility' => 'followers_only',
        'is_private' => true,
    ]);
    $follower = User::factory()->create();

    $follower->follow($owner);
    $owner->approveFollowRequest($follower);

    $service = app(ProfileVisibilityService::class);

    expect($service->canViewFullProfile($follower, $owner))->toBeTrue();
    expect($service->canViewFullProfile(null, $owner))->toBeFalse();
});

it('limits private profiles to the owner', function (): void {
    $owner = User::factory()->create([
        'profile_visibility' => 'private',
        'is_private' => true,
    ]);
    $other = User::factory()->create();

    $service = app(ProfileVisibilityService::class);

    expect($service->canViewFullProfile($owner, $owner))->toBeTrue();
    expect($service->canViewFullProfile($other, $owner))->toBeFalse();
});

it('respects legacy private flags when profile visibility is public', function (): void {
    $owner = User::factory()->create([
        'profile_visibility' => 'public',
        'is_private' => true,
    ]);

    $service = app(ProfileVisibilityService::class);

    expect($service->resolve($owner)->value)->toBe('followers_only');
});
