<?php

use App\Models\Content\Post;
use App\Models\Content\Reaction;
use App\Models\Identity\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

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

    Reaction::query()->create([
        'user_id' => $viewer->getKey(),
        'reactable_type' => (new Post)->getMorphClass(),
        'reactable_id' => $post->getKey(),
        'type' => 'love',
    ]);

    $response = $this->actingAs($viewer)->get(route('explore.index'));
    $posts = $response->viewData('posts');
    $loadedPost = collect($posts->items())->firstWhere('id', $post->getKey());

    $response->assertOk();
    expect($loadedPost)->not->toBeNull();
    expect((bool) ($loadedPost->liked_by_viewer ?? false))->toBeTrue();
    expect((int) ($loadedPost->likes_count ?? -1))->toBe(1);
});

it('loads explore likes count from reactions on initial page load', function (): void {
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

    Reaction::query()->create([
        'user_id' => $liker->getKey(),
        'reactable_type' => (new Post)->getMorphClass(),
        'reactable_id' => $post->getKey(),
        'type' => 'love',
    ]);

    $response = $this->actingAs(User::factory()->create())->get(route('explore.index'));
    $posts = $response->viewData('posts');
    $loadedPost = collect($posts->items())->firstWhere('id', $post->getKey());

    $response->assertOk();
    expect($loadedPost)->not->toBeNull();
    expect((int) ($loadedPost->likes_count ?? -1))->toBe(1);
});
