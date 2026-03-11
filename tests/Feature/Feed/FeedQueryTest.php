<?php

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('loads the feed query path in five queries or fewer', function (): void {
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

    $this->assertQueryCount(5, function () use ($viewer): void {
        Post::query()
            ->forFeed($viewer)
            ->with([
                'author',
                'pet',
                'media',
                'hashtags',
            ])
            ->withCount([
                'likes',
                'comments',
            ])
            ->withExists([
                'likes as liked_by_viewer' => fn ($query) => $query->where('likes.user_id', $viewer->getKey()),
            ])
            ->orderByDesc('posts.created_at')
            ->cursorPaginate(15)
            ->items();
    });
});
