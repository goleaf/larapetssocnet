<?php

use App\Models\Like;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('loads the main feed pagination query in five queries or fewer', function (): void {
    $viewer = User::factory()->create();
    $followed = User::factory()->create();

    $viewer->following()->attach($followed->getKey(), ['status' => 'accepted']);

    Post::factory()->count(10)->create([
        'user_id' => $viewer->getKey(),
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);

    Post::factory()->count(10)->create([
        'user_id' => $followed->getKey(),
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);

    $likedPost = Post::factory()->create([
        'user_id' => $followed->getKey(),
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);

    Like::query()->create([
        'post_id' => $likedPost->getKey(),
        'user_id' => $viewer->getKey(),
        'created_at' => now(),
    ]);

    $viewer->load([
        'acceptedFollowing:id',
    ]);

    $this->actingAs($viewer);

    $posts = null;

    $this->assertQueryCount(5, function () use ($viewer, &$posts): void {
        $posts = Post::paginateMainFeedResults($viewer, null, 15);
        $posts->items();
    });

    $loadedLikedPost = collect($posts?->items())->firstWhere('id', $likedPost->getKey());

    expect($loadedLikedPost)->not->toBeNull();
    expect((bool) ($loadedLikedPost->liked_by_viewer ?? false))->toBeTrue();
});
