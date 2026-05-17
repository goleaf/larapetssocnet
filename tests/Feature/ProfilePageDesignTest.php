<?php

use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Models\Pets\Pet;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders facebook-style profile sections and actions for public profiles', function (): void {
    $profileOwner = User::factory()->create([
        'name' => 'Ava Carter',
        'username' => 'ava_carter01',
        'is_private' => false,
    ]);

    $viewer = User::factory()->create();
    $friend = User::factory()->create([
        'name' => 'Friend User',
        'username' => 'friend_user',
    ]);

    $profileOwner->follow($friend);

    Pet::factory()->for($profileOwner)->create([
        'name' => 'Luna',
        'species' => 'dog',
    ]);

    Post::factory()->for($profileOwner)->create([
        'body' => 'profile-post-visible',
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);

    $this->actingAs($viewer)
        ->get(route('profile.show', ['user' => $profileOwner]))
        ->assertOk()
        ->assertSee('data-ui="profile-shell"', false)
        ->assertSee('data-ui="profile-hero"', false)
        ->assertSee('data-ui="profile-stats"', false)
        ->assertSee('data-ui="profile-stat-card"', false)
        ->assertSee('data-ui="profile-tabs"', false)
        ->assertSee('data-ui="tabs"', false)
        ->assertSee('Intro')
        ->assertSee('Friends')
        ->assertSee('Posts')
        ->assertSee('About')
        ->assertSee('Pets')
        ->assertSee('Photos')
        ->assertSee('Followers')
        ->assertSee('Following')
        ->assertSee('Likes')
        ->assertSee('Follow')
        ->assertSee('Message')
        ->assertSee('min-h-11', false)
        ->assertSee('focus-visible:outline-paw', false)
        ->assertSee('Friend User')
        ->assertSee('profile-post-visible')
        ->assertDontSee('Who To Follow');
});

it('renders a clearer private profile lockup for guests', function (): void {
    $profileOwner = User::factory()->create([
        'name' => 'Private Profile',
        'username' => 'private_profile_design',
        'is_private' => true,
    ]);

    $this->get(route('profile.show', ['user' => $profileOwner]))
        ->assertOk()
        ->assertSee('data-ui="private-profile-shell"', false)
        ->assertSee('data-ui="private-profile-hero"', false)
        ->assertSee('This account is private')
        ->assertSee('Log In to Follow')
        ->assertSee('min-h-11', false);
});
