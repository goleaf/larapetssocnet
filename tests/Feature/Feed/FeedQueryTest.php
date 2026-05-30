<?php

use App\Models\Content\Post;
use App\Models\Content\Reaction;
use App\Models\Identity\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

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

    Reaction::query()->create([
        'reactable_type' => (new Post)->getMorphClass(),
        'reactable_id' => $likedPost->getKey(),
        'user_id' => $viewer->getKey(),
        'type' => Reaction::TYPE_HAHA,
    ]);

    $viewer->load([
        'acceptedFollowing:id',
    ]);

    $this->actingAs($viewer);

    $posts = null;

    $this->assertQueryCount(7, function () use ($viewer, &$posts): void {
        $posts = Post::paginateMainFeedResults($viewer, null, 15);
        $posts->items();
    });

    $loadedLikedPost = collect($posts?->items())->firstWhere('id', $likedPost->getKey());

    collect($posts?->items())->each(function (Post $post): void {
        expect($post->relationLoaded('author'))->toBeTrue();
        expect($post->author?->relationLoaded('media'))->toBeTrue();
    });

    expect($loadedLikedPost)->not->toBeNull();
    expect((bool) ($loadedLikedPost->liked_by_viewer ?? false))->toBeTrue();
    expect($loadedLikedPost->getAttribute('current_user_reaction_type'))->toBe(Reaction::TYPE_HAHA);
});
