<?php

use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Models\Pets\Pet;
use App\Models\Social\FeedMute;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('deduplicates posts that match followed people and followed pet sources', function (): void {
    $viewer = User::factory()->create();
    $author = User::factory()->create();
    $pet = Pet::factory()->for($author)->create();

    $viewer->following()->attach($author->getKey(), ['status' => 'accepted']);
    $viewer->followedPets()->attach($pet->getKey());

    $post = Post::factory()->for($author)->create([
        'body' => 'deduplicated feed post',
        'created_at' => now(),
    ]);
    $post->pets()->attach($pet->getKey());

    $postIds = Post::query()
        ->forFeed((int) $viewer->getKey())
        ->whereKey($post->getKey())
        ->pluck('posts.id');

    expect($postIds->all())->toBe([$post->getKey()]);
});

it('excludes muted authors from the main feed scope', function (): void {
    $viewer = User::factory()->create();
    $author = User::factory()->create();

    $viewer->following()->attach($author->getKey(), ['status' => 'accepted']);

    $mutedPost = Post::factory()->for($author)->create();

    FeedMute::query()->create([
        'user_id' => $viewer->getKey(),
        'mutable_type' => $author->getMorphClass(),
        'mutable_id' => $author->getKey(),
    ]);

    $postIds = Post::query()
        ->forFeed((int) $viewer->getKey())
        ->pluck('posts.id');

    expect($postIds)->not->toContain($mutedPost->getKey());
});

it('excludes muted normalized pet tags from the main feed scope', function (): void {
    $viewer = User::factory()->create();
    $owner = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create();

    $viewer->followedPets()->attach($pet->getKey());

    $mutedPetPost = Post::factory()->for($owner)->create();
    $mutedPetPost->pets()->attach($pet->getKey());

    FeedMute::query()->create([
        'user_id' => $viewer->getKey(),
        'mutable_type' => $pet->getMorphClass(),
        'mutable_id' => $pet->getKey(),
    ]);

    $postIds = Post::query()
        ->forFeed((int) $viewer->getKey())
        ->pluck('posts.id');

    expect($postIds)->not->toContain($mutedPetPost->getKey());
});
