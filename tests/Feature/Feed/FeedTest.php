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
        ->assertSee('data-ui="feed-livewire-page"', false)
        ->assertSee('data-ui="feed-left-sidebar-skeleton"', false)
        ->assertSee('data-ui="feed-right-sidebar-skeleton"', false)
        ->assertSee('data-ui="feed-stream"', false)
        ->assertDontSee('post-from-stranger');
});

it('filters the feed to people sources', function (): void {
    $viewer = User::factory()->create();
    $followedUser = User::factory()->create();
    $petOwner = User::factory()->create();

    $viewer->following()->attach($followedUser->getKey(), ['status' => 'accepted']);

    $followedPet = Pet::factory()->for($petOwner)->create();
    $viewer->followedPets()->attach($followedPet->getKey());

    Post::factory()->for($followedUser)->create([
        'body' => 'people-source-post',
        'body_html' => '<p>people-source-post</p>',
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);

    Post::factory()->for($petOwner)->create([
        'pet_id' => $followedPet->getKey(),
        'body' => 'pet-source-post',
        'body_html' => '<p>pet-source-post</p>',
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);

    $this->actingAs($viewer)
        ->get(route('feed.index', ['source' => 'people']))
        ->assertOk()
        ->assertSee('data-ui="feed-stream"', false)
        ->assertDontSee('pet-source-post');
});

it('filters the feed to pet sources', function (): void {
    $viewer = User::factory()->create();
    $followedUser = User::factory()->create();
    $petOwner = User::factory()->create();

    $viewer->following()->attach($followedUser->getKey(), ['status' => 'accepted']);

    $followedPet = Pet::factory()->for($petOwner)->create();
    $viewer->followedPets()->attach($followedPet->getKey());

    Post::factory()->for($followedUser)->create([
        'body' => 'people-source-post',
        'body_html' => '<p>people-source-post</p>',
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);

    Post::factory()->for($petOwner)->create([
        'pet_id' => $followedPet->getKey(),
        'body' => 'pet-source-post',
        'body_html' => '<p>pet-source-post</p>',
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);

    $this->actingAs($viewer)
        ->get(route('feed.index', ['source' => 'pets']))
        ->assertOk()
        ->assertSee('data-ui="feed-stream"', false)
        ->assertDontSee('people-source-post');
});

it('ignores unknown feed source filters', function (): void {
    $viewer = User::factory()->create();
    $followedUser = User::factory()->create();
    $petOwner = User::factory()->create();

    $viewer->following()->attach($followedUser->getKey(), ['status' => 'accepted']);

    $followedPet = Pet::factory()->for($petOwner)->create();
    $viewer->followedPets()->attach($followedPet->getKey());

    Post::factory()->for($followedUser)->create([
        'body' => 'unknown-filter-people-post',
        'body_html' => '<p>unknown-filter-people-post</p>',
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);

    Post::factory()->for($petOwner)->create([
        'pet_id' => $followedPet->getKey(),
        'body' => 'unknown-filter-pet-post',
        'body_html' => '<p>unknown-filter-pet-post</p>',
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);

    $this->actingAs($viewer)
        ->get(route('feed.index', ['source' => 'groups']))
        ->assertOk()
        ->assertSee('data-ui="feed-stream"', false);
});
