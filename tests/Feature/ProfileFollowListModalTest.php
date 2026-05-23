<?php

use App\Models\Identity\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('renders followers and following lists through the same mode-driven livewire modal', function (): void {
    $profileOwner = User::factory()->create([
        'username' => 'modal_profile_owner',
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);
    $follower = User::factory()->create([
        'name' => 'Reusable Follower User',
        'username' => 'reusable_follower_user',
    ]);
    $followedUser = User::factory()->create([
        'name' => 'Reusable Following User',
        'username' => 'reusable_following_user',
    ]);

    $follower->follow($profileOwner);
    $profileOwner->follow($followedUser);

    $viewer = User::factory()->create();

    Livewire::actingAs($viewer)
        ->test('profile.follow-list-modal', [
            'profileUserId' => $profileOwner->getKey(),
            'mode' => 'followers',
            'total' => 1,
        ])
        ->assertSee('data-ui="profile-followers-modal"', false)
        ->assertSee('data-ui="profile-followers-modal-list"', false)
        ->assertSee('data-ui="profile-followers-modal-user"', false)
        ->assertSee('Followers')
        ->assertSee('Reusable Follower User')
        ->assertSee('@reusable_follower_user')
        ->assertSee('View all followers')
        ->assertDontSee('Reusable Following User');

    Livewire::actingAs($viewer)
        ->test('profile.follow-list-modal', [
            'profileUserId' => $profileOwner->getKey(),
            'mode' => 'following',
            'total' => 1,
        ])
        ->assertSee('data-ui="profile-following-modal"', false)
        ->assertSee('data-ui="profile-following-modal-list"', false)
        ->assertSee('data-ui="profile-following-modal-user"', false)
        ->assertSee('Following')
        ->assertSee('Reusable Following User')
        ->assertSee('@reusable_following_user')
        ->assertSee('View all following')
        ->assertDontSee('Reusable Follower User');
});

it('enforces profile following visibility inside the reusable modal component', function (): void {
    $profileOwner = User::factory()->create([
        'username' => 'closed_following_modal_owner',
        'is_private' => false,
        'profile_visibility' => 'public',
        'open_following' => false,
    ]);

    $profileOwner->follow(User::factory()->create());

    Livewire::actingAs(User::factory()->create())
        ->test('profile.follow-list-modal', [
            'profileUserId' => $profileOwner->getKey(),
            'mode' => 'following',
        ])
        ->assertForbidden();
});
