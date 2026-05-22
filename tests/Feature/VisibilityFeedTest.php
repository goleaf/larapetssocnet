<?php

use App\Models\Content\Post;
use App\Models\Identity\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('feed shows followed users public and followers posts, excludes private and non-followed users', function (): void {
    $viewer = User::factory()->create();
    $followed = User::factory()->create();
    $mutual = User::factory()->create();
    $other = User::factory()->create();

    $viewer->follow($followed);
    $followed->approveFollowRequest($viewer);
    $viewer->follow($mutual);
    $mutual->approveFollowRequest($viewer);
    $mutual->follow($viewer);

    Post::factory()->for($viewer)->create(['body' => 'viewer-public', 'body_html' => '<p>viewer-public</p>', 'visibility' => Post::VISIBILITY_PUBLIC]);
    Post::factory()->for($viewer)->create(['body' => 'viewer-followers', 'body_html' => '<p>viewer-followers</p>', 'visibility' => Post::VISIBILITY_FOLLOWERS]);
    Post::factory()->for($viewer)->create(['body' => 'viewer-friends', 'body_html' => '<p>viewer-friends</p>', 'visibility' => Post::VISIBILITY_FRIENDS]);
    Post::factory()->for($viewer)->create(['body' => 'viewer-private', 'body_html' => '<p>viewer-private</p>', 'visibility' => Post::VISIBILITY_PRIVATE]);
    Post::factory()->for($followed)->create(['body' => 'followed-public', 'body_html' => '<p>followed-public</p>', 'visibility' => Post::VISIBILITY_PUBLIC]);
    Post::factory()->for($followed)->create(['body' => 'followed-followers', 'body_html' => '<p>followed-followers</p>', 'visibility' => Post::VISIBILITY_FOLLOWERS]);
    Post::factory()->for($followed)->create(['body' => 'followed-friends', 'body_html' => '<p>followed-friends</p>', 'visibility' => Post::VISIBILITY_FRIENDS]);
    Post::factory()->for($followed)->create(['body' => 'followed-private', 'body_html' => '<p>followed-private</p>', 'visibility' => Post::VISIBILITY_PRIVATE]);
    Post::factory()->for($mutual)->create(['body' => 'mutual-friends', 'body_html' => '<p>mutual-friends</p>', 'visibility' => Post::VISIBILITY_FRIENDS]);
    Post::factory()->for($other)->create(['body' => 'other-public', 'body_html' => '<p>other-public</p>', 'visibility' => Post::VISIBILITY_PUBLIC]);

    $this->actingAs($viewer)
        ->get(route('feed.index'))
        ->assertOk()
        ->assertSee('viewer-public')
        ->assertSee('viewer-followers')
        ->assertSee('viewer-friends')
        ->assertSee('viewer-private')
        ->assertSee('followed-public')
        ->assertSee('followed-followers')
        ->assertDontSee('followed-friends')
        ->assertDontSee('followed-private')
        ->assertSee('mutual-friends')
        ->assertDontSee('other-public');
});
