<?php

use App\Models\Content\Post;
use App\Models\Identity\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('increments post views for non-author feed and profile renders only', function (): void {
    $author = User::factory()->create();
    $viewer = User::factory()->create();
    $post = Post::factory()->for($author)->create([
        'status' => 'published',
        'visibility' => Post::VISIBILITY_PUBLIC,
        'view_count' => 0,
    ]);

    $this->actingAs($viewer);
    Blade::render('<x-post-card :post="$post" />', ['post' => $post]);

    expect($post->fresh()->view_count)->toBe(1);

    Blade::render('<x-post-card :post="$post" context="detail" />', ['post' => $post->fresh()]);

    expect($post->fresh()->view_count)->toBe(1);

    $this->actingAs($author);
    Blade::render('<x-post-card :post="$post" context="profile" />', ['post' => $post->fresh()]);

    expect($post->fresh()->view_count)->toBe(1);
});

it('renders the analytics trigger only for the post author', function (): void {
    $author = User::factory()->create();
    $viewer = User::factory()->create();
    $post = Post::factory()->for($author)->create();

    $this->actingAs($author);
    $authorHtml = Blade::render('<x-post-card :post="$post" />', ['post' => $post]);

    $this->actingAs($viewer);
    $viewerHtml = Blade::render('<x-post-card :post="$post" />', ['post' => $post->fresh()]);

    expect($authorHtml)
        ->toContain('data-ui="post-card-analytics-trigger"')
        ->and($viewerHtml)
        ->not->toContain('data-ui="post-card-analytics-trigger"');
});

it('shows post analytics metrics and the comparison chart to the author', function (): void {
    $author = User::factory()->create(['followers_count' => 120]);
    $reposter = User::factory()->create(['followers_count' => 40]);
    $secondReposter = User::factory()->create(['followers_count' => 10]);
    $post = Post::factory()->for($author)->create([
        'view_count' => 321,
        'reactions_count' => 9,
        'paw_count' => 2,
        'love_count' => 3,
        'haha_count' => 1,
        'wow_count' => 1,
        'sad_count' => 1,
        'angry_count' => 1,
        'comments_count' => 4,
        'shares_count' => 5,
    ]);

    Post::factory()->for($reposter)->create([
        'original_post_id' => $post->id,
        'status' => 'published',
    ]);
    Post::factory()->for($secondReposter)->create([
        'original_post_id' => $post->id,
        'status' => 'published',
    ]);

    Post::factory()->for($author)->create([
        'view_count' => 100,
        'reactions_count' => 6,
        'comments_count' => 2,
        'shares_count' => 1,
    ]);
    Post::factory()->for($author)->create([
        'view_count' => 200,
        'reactions_count' => 10,
        'comments_count' => 4,
        'shares_count' => 3,
    ]);

    Livewire::actingAs($author)
        ->test('posts.analytics-trigger', ['post' => $post])
        ->assertSee('View post analytics')
        ->call('open')
        ->assertSet('open', true)
        ->assertSee('Total views')
        ->assertSee('321')
        ->assertSee('Total reactions')
        ->assertSee('Love')
        ->assertSee('3')
        ->assertSee('Estimated reach')
        ->assertSee('170')
        ->assertSee('aria-label="Post engagement comparison chart"', false);
});

it('blocks non-authors from opening analytics directly', function (): void {
    $author = User::factory()->create();
    $viewer = User::factory()->create();
    $post = Post::factory()->for($author)->create();

    Livewire::actingAs($viewer)
        ->test('posts.analytics-trigger', ['post' => $post])
        ->call('open')
        ->assertForbidden();
});
