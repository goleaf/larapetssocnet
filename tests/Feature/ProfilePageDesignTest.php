<?php

use App\Models\Pet;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

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
        ->assertSee('Friend User')
        ->assertSee('profile-post-visible')
        ->assertDontSee('Who To Follow');
});
