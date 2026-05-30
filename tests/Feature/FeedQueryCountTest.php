<?php

use App\Models\Content\Post;
use App\Models\Identity\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('loads feed pagination path without n+1 queries for 15 posts', function (): void {
    $viewer = User::factory()->create();
    $followed = User::factory()->create();

    $viewer->following()->attach($followed->getKey(), ['status' => 'accepted']);

    Post::factory()->count(8)->create([
        'user_id' => $viewer->getKey(),
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);

    Post::factory()->count(8)->create([
        'user_id' => $followed->getKey(),
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);

    $viewer->load([
        'acceptedFollowing:id',
    ]);

    $this->assertQueryCount(6, function () use ($viewer): void {
        Post::paginateMainFeedResults($viewer, null, 15)->items();
    });
});
