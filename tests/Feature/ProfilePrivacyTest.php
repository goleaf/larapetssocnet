<?php

use App\Models\Identity\User;
use App\Services\UsernameService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows public profiles to guests', function (): void {
    $user = User::factory()->create([
        'username' => 'public_user',
        'profile_visibility' => 'public',
        'is_private' => false,
    ]);

    $this->get(route('profile.show', ['user' => $user]))
        ->assertOk()
        ->assertSee('@public_user');
});

it('owners can view their own private profiles', function (): void {
    $user = User::factory()->create([
        'username' => 'private_owner',
        'profile_visibility' => 'private',
        'is_private' => true,
    ]);

    $this->actingAs($user)
        ->get(route('profile.show', ['user' => $user]))
        ->assertOk()
        ->assertSee('@private_owner');
});

it('accepted followers can view followers-only profiles', function (): void {
    $owner = User::factory()->create([
        'username' => 'followers_only',
        'profile_visibility' => 'followers_only',
        'is_private' => true,
    ]);
    $follower = User::factory()->create();

    $follower->follow($owner);
    $owner->approveFollowRequest($follower);

    $this->actingAs($follower)
        ->get(route('profile.show', ['user' => $owner]))
        ->assertOk()
        ->assertSee('@followers_only');
});

it('pending followers cannot view followers-only profiles', function (): void {
    $owner = User::factory()->create([
        'username' => 'pending_owner',
        'profile_visibility' => 'followers_only',
        'is_private' => true,
    ]);
    $requester = User::factory()->create();

    $requester->follow($owner);

    $this->actingAs($requester)
        ->get(route('profile.show', ['user' => $owner]))
        ->assertOk()
        ->assertSee('followers-only');
});

it('strangers cannot view private profiles', function (): void {
    $owner = User::factory()->create([
        'username' => 'strict_private',
        'profile_visibility' => 'private',
        'is_private' => true,
    ]);

    $this->get(route('profile.show', ['user' => $owner]))
        ->assertOk()
        ->assertSee('Only you can view this profile');
});

it('blocked users cannot access profiles or username redirects', function (): void {
    $owner = User::factory()->create([
        'username' => 'oldname',
        'profile_visibility' => 'public',
        'is_private' => false,
    ]);
    $viewer = User::factory()->create();

    app(UsernameService::class)->change($owner, 'newname', $owner);
    $owner->block($viewer);

    $this->actingAs($viewer)
        ->get(route('profile.show', ['user' => $owner]))
        ->assertNotFound();

    $this->actingAs($viewer)
        ->get(route('profile.show', ['user' => 'oldname']))
        ->assertNotFound();
});

it('followers list respects privacy settings', function (): void {
    $owner = User::factory()->create([
        'profile_visibility' => 'private',
        'is_private' => true,
    ]);
    $viewer = User::factory()->create();

    $this->actingAs($viewer)
        ->get(route('profile.followers', ['user' => $owner]))
        ->assertForbidden();
});
