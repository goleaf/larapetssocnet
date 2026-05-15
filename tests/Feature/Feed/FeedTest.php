<?php

use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Models\Pets\Pet;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows posts from followed users and followed pets', function (): void {
    $viewer = User::factory()->create();
    $followedUser = User::factory()->create();
    $petOwner = User::factory()->create();
    $stranger = User::factory()->create();

    $viewer->following()->attach($followedUser->getKey(), ['status' => 'accepted']);

    $followedPet = Pet::factory()->for($petOwner)->create();
    $viewer->followedPets()->attach($followedPet->getKey());

    Post::factory()->for($followedUser)->create([
        'body' => 'post-from-followed-user',
        'body_html' => '<p>post-from-followed-user</p>',
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);

    Post::factory()->for($petOwner)->create([
        'pet_id' => $followedPet->getKey(),
        'body' => 'post-from-followed-pet',
        'body_html' => '<p>post-from-followed-pet</p>',
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);

    Post::factory()->for($stranger)->create([
        'body' => 'post-from-stranger',
        'body_html' => '<p>post-from-stranger</p>',
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);

    $this->actingAs($viewer)
        ->get(route('feed.index'))
        ->assertOk()
        ->assertSee('post-from-followed-user')
        ->assertSee('post-from-followed-pet')
        ->assertDontSee('post-from-stranger');
});
