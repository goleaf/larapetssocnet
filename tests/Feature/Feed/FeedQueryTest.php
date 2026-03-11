<?php

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

    $viewer->load([
        'acceptedFollowing:id',
    ]);

    $this->assertQueryCount(5, function () use ($viewer): void {
        Post::paginateMainFeedResults($viewer, null, 15)->items();
    });
});
