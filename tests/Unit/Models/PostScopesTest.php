<?php

use App\Enums\PostStatus;
use App\Models\Follow;
use App\Models\Hashtag;
use App\Models\Pet;
use App\Models\Post;
use App\Models\PostMedia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('filters published posts', function (): void {
    $publishedPost = Post::factory()->create(['status' => PostStatus::Published->value]);
    $draftPost = Post::factory()->create(['status' => PostStatus::Draft->value]);

    $postIds = Post::query()
        ->published()
        ->pluck('posts.id');

    expect($postIds)
        ->toContain($publishedPost->getKey())
        ->not->toContain($draftPost->getKey());
});

it('returns feed posts for owner and accepted following', function (): void {
    $viewer = User::factory()->create();
    $followed = User::factory()->create();
    $petOwner = User::factory()->create();
    $followedPet = Pet::factory()->for($petOwner)->create();
    $notFollowed = User::factory()->create();

    Follow::factory()->create([
        'follower_id' => $viewer->getKey(),
        'following_id' => $followed->getKey(),
        'status' => 'accepted',
    ]);

    Follow::factory()->create([
        'follower_id' => $viewer->getKey(),
        'following_id' => $notFollowed->getKey(),
        'status' => 'pending',
    ]);

    $viewer->followedPets()->attach($followedPet->getKey());

    $ownPost = Post::factory()->create([
        'user_id' => $viewer->getKey(),
        'visibility' => Post::VISIBILITY_PUBLIC,
        'status' => 'published',
    ]);

    $followedPost = Post::factory()->create([
        'user_id' => $followed->getKey(),
        'visibility' => Post::VISIBILITY_PUBLIC,
        'status' => 'published',
    ]);

    $followedPetPost = Post::factory()->create([
        'user_id' => $petOwner->getKey(),
        'pet_id' => $followedPet->getKey(),
        'visibility' => Post::VISIBILITY_PUBLIC,
        'status' => 'published',
    ]);

    $pendingFollowPost = Post::factory()->create([
        'user_id' => $notFollowed->getKey(),
        'visibility' => Post::VISIBILITY_PUBLIC,
        'status' => 'published',
    ]);

    $postIds = Post::query()
        ->forFeed($viewer->getKey())
        ->pluck('posts.id');

    expect($postIds)
        ->toContain($ownPost->getKey())
        ->toContain($followedPost->getKey())
        ->toContain($followedPetPost->getKey())
        ->not->toContain($pendingFollowPost->getKey());
});

it('filters posts by pet id', function (): void {
    $pet = Pet::factory()->create();
    $otherPet = Pet::factory()->create();

    $petPost = Post::factory()->create(['pet_id' => $pet->getKey()]);
    $otherPetPost = Post::factory()->create(['pet_id' => $otherPet->getKey()]);

    $postIds = Post::query()
        ->byPet($pet->getKey())
        ->pluck('posts.id');

    expect($postIds)
        ->toContain($petPost->getKey())
        ->not->toContain($otherPetPost->getKey());
});

it('returns only posts that have media', function (): void {
    $postWithMedia = Post::factory()->create();
    $postWithoutMedia = Post::factory()->create();

    PostMedia::factory()->create([
        'post_id' => $postWithMedia->getKey(),
        'media_type' => 'image',
    ]);

    $postIds = Post::query()
        ->withMedia()
        ->pluck('posts.id');

    expect($postIds)
        ->toContain($postWithMedia->getKey())
        ->not->toContain($postWithoutMedia->getKey());
});

it('filters posts by hashtag slug', function (): void {
    $matchingTag = Hashtag::factory()->create([
        'name' => 'Pets',
        'slug' => 'pets',
    ]);
    $otherTag = Hashtag::factory()->create([
        'name' => 'Travel',
        'slug' => 'travel',
    ]);

    $matchingPost = Post::factory()->create();
    $otherPost = Post::factory()->create();

    $matchingPost->hashtags()->attach($matchingTag->getKey());
    $otherPost->hashtags()->attach($otherTag->getKey());

    $postIds = Post::query()
        ->byTag('pets')
        ->pluck('posts.id');

    expect($postIds)
        ->toContain($matchingPost->getKey())
        ->not->toContain($otherPost->getKey());
});
