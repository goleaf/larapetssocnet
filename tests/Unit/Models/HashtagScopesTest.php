<?php

use App\Models\Hashtag;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('orders hashtags by popularity', function (): void {
    Hashtag::factory()->create([
        'name' => 'Low',
        'slug' => 'low',
        'posts_count' => 2,
    ]);

    $mostPopular = Hashtag::factory()->create([
        'name' => 'Top',
        'slug' => 'top',
        'posts_count' => 20,
    ]);

    Hashtag::factory()->create([
        'name' => 'Middle',
        'slug' => 'middle',
        'posts_count' => 7,
    ]);

    $firstId = Hashtag::query()
        ->popular()
        ->value('hashtags.id');

    expect($firstId)->toBe($mostPopular->getKey());
});

it('filters hashtags by post type', function (): void {
    $photoTag = Hashtag::factory()->create([
        'name' => 'Photo',
        'slug' => 'photo',
    ]);
    $textTag = Hashtag::factory()->create([
        'name' => 'Text',
        'slug' => 'text',
    ]);

    $photoPost = Post::factory()->create(['type' => Post::TYPE_PHOTO]);
    $textPost = Post::factory()->create(['type' => Post::TYPE_TEXT]);

    $photoPost->hashtags()->attach($photoTag->getKey());
    $textPost->hashtags()->attach($textTag->getKey());

    $hashtagIds = Hashtag::query()
        ->forType(Post::TYPE_PHOTO)
        ->pluck('hashtags.id');

    expect($hashtagIds)
        ->toContain($photoTag->getKey())
        ->not->toContain($textTag->getKey());
});
