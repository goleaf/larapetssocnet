<?php

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('feed shows followed users public and followers posts, excludes private and non-followed users', function (): void {
    $viewer = User::factory()->create();
    $followed = User::factory()->create();
    $other = User::factory()->create();

    $viewer->follow($followed);
    $followed->approveFollowRequest($viewer);

    Post::factory()->for($viewer)->create(['body' => 'viewer-public', 'body_html' => '<p>viewer-public</p>', 'visibility' => Post::VISIBILITY_PUBLIC]);
    Post::factory()->for($viewer)->create(['body' => 'viewer-followers', 'body_html' => '<p>viewer-followers</p>', 'visibility' => Post::VISIBILITY_FOLLOWERS]);
    Post::factory()->for($viewer)->create(['body' => 'viewer-private', 'body_html' => '<p>viewer-private</p>', 'visibility' => Post::VISIBILITY_PRIVATE]);
    Post::factory()->for($followed)->create(['body' => 'followed-public', 'body_html' => '<p>followed-public</p>', 'visibility' => Post::VISIBILITY_PUBLIC]);
    Post::factory()->for($followed)->create(['body' => 'followed-followers', 'body_html' => '<p>followed-followers</p>', 'visibility' => Post::VISIBILITY_FOLLOWERS]);
    Post::factory()->for($followed)->create(['body' => 'followed-private', 'body_html' => '<p>followed-private</p>', 'visibility' => Post::VISIBILITY_PRIVATE]);
    Post::factory()->for($other)->create(['body' => 'other-public', 'body_html' => '<p>other-public</p>', 'visibility' => Post::VISIBILITY_PUBLIC]);

    $this->actingAs($viewer)
        ->get(route('feed.index'))
        ->assertOk()
        ->assertSee('viewer-public')
        ->assertSee('viewer-followers')
        ->assertSee('viewer-private')
        ->assertSee('followed-public')
        ->assertSee('followed-followers')
        ->assertDontSee('followed-private')
        ->assertDontSee('other-public');
});
