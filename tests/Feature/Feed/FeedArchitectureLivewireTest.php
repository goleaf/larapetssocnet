<?php

use App\Models\Content\Post;
use App\Models\Identity\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the feed as a full-page livewire shell with lazy sidebars and eager center stream', function (): void {
    $viewer = User::factory()->create();

    $this->actingAs($viewer)
        ->get(route('feed.index'))
        ->assertOk()
        ->assertSee('wire:name="pages.feed.index"', false)
        ->assertSee('data-ui="feed-livewire-page"', false)
        ->assertSee('data-ui="feed-stream"', false)
        ->assertSee('data-ui="feed-left-sidebar-skeleton"', false)
        ->assertSee('data-ui="feed-right-sidebar-skeleton"', false)
        ->assertDontSee('data-ui="feed-stream-skeleton"', false);
});

it('builds the main feed eligibility query from precomputed feed items with relationship fallback', function (): void {
    $viewer = User::factory()->create();

    $sql = strtolower(Post::query()->forFeed((int) $viewer->getKey())->toSql());

    expect($sql)
        ->toContain('feed_items')
        ->toContain('union')
        ->toContain('feed_post_ids')
        ->toContain('pet_followers')
        ->toContain('follows')
        ->toContain('posts"."visibility');
});

it('renders feed post cards as independent livewire components', function (): void {
    $viewer = User::factory()->create();

    Post::factory()->for($viewer)->create([
        'body' => 'island-post-card-body',
    ]);

    $this->actingAs($viewer)
        ->get(route('feed.index'))
        ->assertOk()
        ->assertSee('wire:name="posts.card"', false)
        ->assertSee('data-ui="feed-post-livewire-card"', false);
});
