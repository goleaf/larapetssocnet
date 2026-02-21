<?php

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('shows feed content with visibility and block filters applied', function (): void {
    $viewer = User::factory()->create();
    $followed = User::factory()->create();
    $unfollowed = User::factory()->create();
    $blocked = User::factory()->create();

    $viewer->following()->attach($followed->id, ['status' => 'accepted']);
    $viewer->blockedUsers()->attach($blocked->id);

    $ownPrivatePost = Post::factory()->create([
        'user_id' => $viewer->id,
        'body' => 'own-private-post',
        'visibility' => 'private',
    ]);

    $followedFollowersPost = Post::factory()->create([
        'user_id' => $followed->id,
        'body' => 'followed-followers-post',
        'visibility' => 'followers',
    ]);

    Post::factory()->create([
        'user_id' => $followed->id,
        'body' => 'followed-private-post',
        'visibility' => 'private',
    ]);

    Post::factory()->create([
        'user_id' => $unfollowed->id,
        'body' => 'unfollowed-public-post',
        'visibility' => 'public',
    ]);

    Post::factory()->create([
        'user_id' => $blocked->id,
        'body' => 'blocked-public-post',
        'visibility' => 'public',
    ]);

    $response = $this->actingAs($viewer)->get(route('feed.index'));

    $response->assertOk();
    $response->assertSee($ownPrivatePost->body);
    $response->assertSee($followedFollowersPost->body);
    $response->assertDontSee('followed-private-post');
    $response->assertDontSee('unfollowed-public-post');
    $response->assertDontSee('blocked-public-post');
});

it('shows feed navigation actions and share action on post cards', function (): void {
    $viewer = User::factory()->create();

    Post::factory()->create([
        'user_id' => $viewer->id,
        'body' => 'shareable-post',
        'visibility' => 'public',
    ]);

    $this->actingAs($viewer)
        ->get(route('feed.index'))
        ->assertOk()
        ->assertSee('Saved')
        ->assertSee('Explore')
        ->assertSee('Create')
        ->assertSee('Share');
});

it('paginates feed posts at 15 items per page', function (): void {
    $viewer = User::factory()->create();

    Post::factory()
        ->count(16)
        ->create([
            'user_id' => $viewer->id,
            'visibility' => 'public',
        ])
        ->values()
        ->tap(function ($posts): void {
            $posts->each(fn (Post $post, int $index) => $post->update(['body' => 'feed-page-post-'.$index]));
        });

    $firstPage = $this->actingAs($viewer)->get(route('feed.index'));

    $firstPage->assertOk();
    $firstPage->assertSee('feed-page-post-15');
    $firstPage->assertSee('feed-page-post-1');
    $firstPage->assertDontSee('feed-page-post-0');

    $secondPage = $this->actingAs($viewer)->get(route('feed.index', ['page' => 2]));

    $secondPage->assertOk();
    $secondPage->assertSee('feed-page-post-0');
});
