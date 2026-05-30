<?php

use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Models\Pets\Pet;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('renders followed people, followed pet tags, and own posts in the live feed stream', function (): void {
    $viewer = User::factory()->create();
    $followedUser = User::factory()->create();
    $petOwner = User::factory()->create();
    $stranger = User::factory()->create();

    $viewer->following()->attach($followedUser->getKey(), ['status' => 'accepted']);

    $followedPet = Pet::factory()->for($petOwner)->create(['name' => 'Miso']);
    $viewer->followedPets()->attach($followedPet->getKey());

    Post::factory()->for($viewer)->create([
        'body' => 'own-feed-stream-post',
        'created_at' => now()->subMinutes(1),
    ]);

    Post::factory()->for($followedUser)->create([
        'body' => 'followed-person-stream-post',
        'created_at' => now()->subMinutes(2),
    ]);

    $taggedPetPost = Post::factory()->for($petOwner)->create([
        'body' => 'followed-normalized-pet-stream-post',
        'created_at' => now()->subMinutes(3),
    ]);
    $taggedPetPost->pets()->attach($followedPet->getKey());

    Post::factory()->for($stranger)->create([
        'body' => 'stranger-stream-post',
        'created_at' => now()->subMinutes(4),
    ]);

    Livewire::actingAs($viewer)
        ->test('feed.stream')
        ->assertSee('own-feed-stream-post')
        ->assertSee('followed-person-stream-post')
        ->assertSee('followed-normalized-pet-stream-post')
        ->assertSee('Miso')
        ->assertDontSee('stranger-stream-post');
});

it('switches feed source filters without a page reload', function (): void {
    $viewer = User::factory()->create();
    $followedUser = User::factory()->create();
    $petOwner = User::factory()->create();

    $viewer->following()->attach($followedUser->getKey(), ['status' => 'accepted']);

    $followedPet = Pet::factory()->for($petOwner)->create();
    $viewer->followedPets()->attach($followedPet->getKey());

    Post::factory()->for($followedUser)->create([
        'body' => 'people-only-livewire-post',
        'created_at' => now()->subMinutes(1),
    ]);

    Post::factory()->for($petOwner)->create([
        'pet_id' => $followedPet->getKey(),
        'body' => 'pets-only-livewire-post',
        'created_at' => now()->subMinutes(2),
    ]);

    Livewire::actingAs($viewer)
        ->test('feed.stream')
        ->call('setSource', 'people')
        ->assertSet('source', 'people')
        ->assertSee('people-only-livewire-post')
        ->assertDontSee('pets-only-livewire-post')
        ->call('setSource', 'pets')
        ->assertSet('source', 'pets')
        ->assertSee('pets-only-livewire-post')
        ->assertDontSee('people-only-livewire-post');
});

it('appends older posts through the infinite scroll action', function (): void {
    $viewer = User::factory()->create();

    $posts = Post::factory()
        ->count(20)
        ->sequence(fn (Sequence $sequence): array => [
            'body' => 'paginated-live-feed-post-'.$sequence->index,
            'created_at' => now()->subMinutes($sequence->index),
        ])
        ->create([
            'user_id' => $viewer->getKey(),
            'visibility' => Post::VISIBILITY_PUBLIC,
        ])
        ->sortByDesc('created_at')
        ->values();

    Livewire::actingAs($viewer)
        ->test('feed.stream')
        ->assertSet('postIds', $posts->take(15)->pluck('id')->all())
        ->assertSet('hasMorePosts', true)
        ->call('loadMore')
        ->assertSet('postIds', $posts->pluck('id')->all())
        ->assertSet('hasMorePosts', false);
});

it('counts and prepends new posts without disturbing existing scroll state', function (): void {
    $viewer = User::factory()->create();

    Post::factory()->for($viewer)->create([
        'body' => 'existing-live-feed-post',
        'created_at' => now()->subMinutes(5),
    ]);

    $component = Livewire::actingAs($viewer)
        ->test('feed.stream')
        ->assertSee('existing-live-feed-post')
        ->assertSet('newPostsCount', 0);

    Post::factory()->for($viewer)->create([
        'body' => 'newly-polled-live-feed-post',
        'created_at' => now(),
    ]);

    $component
        ->call('checkForNewPosts')
        ->assertSet('newPostsCount', 1)
        ->call('loadNewPosts')
        ->assertSet('newPostsCount', 0)
        ->assertSee('newly-polled-live-feed-post');
});
