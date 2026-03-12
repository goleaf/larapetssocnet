<?php

use App\Models\Like;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('shows the viewers liked posts as liked on explore after refresh', function (): void {
    $viewer = User::factory()->create();
    $author = User::factory()->create([
        'is_private' => false,
        'is_banned' => false,
    ]);

    $post = Post::factory()->for($author)->create([
        'status' => 'published',
        'visibility' => Post::VISIBILITY_PUBLIC,
        'likes_count' => 0,
    ]);

    Like::query()->create([
        'user_id' => $viewer->getKey(),
        'post_id' => $post->getKey(),
        'created_at' => now(),
    ]);

    $response = $this->actingAs($viewer)->get(route('explore.index'));
    $posts = $response->viewData('posts');
    $loadedPost = collect($posts->items())->firstWhere('id', $post->getKey());

    $response->assertOk();
    expect($loadedPost)->not->toBeNull();
    expect((bool) ($loadedPost->liked_by_viewer ?? false))->toBeTrue();
    expect((int) ($loadedPost->likes_count ?? -1))->toBe(1);
});

it('loads explore likes count from the likes table on initial page load', function (): void {
    $author = User::factory()->create([
        'is_private' => false,
        'is_banned' => false,
    ]);
    $liker = User::factory()->create();

    $post = Post::factory()->for($author)->create([
        'status' => 'published',
        'visibility' => Post::VISIBILITY_PUBLIC,
        'likes_count' => 27,
    ]);

    Like::query()->create([
        'user_id' => $liker->getKey(),
        'post_id' => $post->getKey(),
        'created_at' => now(),
    ]);

    $response = $this->get(route('explore.index'));
    $posts = $response->viewData('posts');
    $loadedPost = collect($posts->items())->firstWhere('id', $post->getKey());

    $response->assertOk();
    expect($loadedPost)->not->toBeNull();
    expect((int) ($loadedPost->likes_count ?? -1))->toBe(1);
});
