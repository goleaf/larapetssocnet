<?php

use App\Models\Content\Post;
use App\Models\Identity\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('explore only shows public posts from public and non-banned authors', function (): void {
    $publicAuthor = User::factory()->create(['is_private' => false, 'is_banned' => false]);
    $privateAuthor = User::factory()->create(['is_private' => true, 'is_banned' => false]);
    $bannedAuthor = User::factory()->create(['is_private' => false, 'is_banned' => true]);

    Post::factory()->for($publicAuthor)->create(['body' => 'explore-public-visible', 'visibility' => Post::VISIBILITY_PUBLIC]);
    Post::factory()->for($publicAuthor)->create(['body' => 'explore-followers-hidden', 'visibility' => Post::VISIBILITY_FOLLOWERS]);
    Post::factory()->for($publicAuthor)->create(['body' => 'explore-private-hidden', 'visibility' => Post::VISIBILITY_PRIVATE]);
    Post::factory()->for($privateAuthor)->create(['body' => 'explore-private-account-hidden', 'visibility' => Post::VISIBILITY_PUBLIC]);
    Post::factory()->for($bannedAuthor)->create(['body' => 'explore-banned-hidden', 'visibility' => Post::VISIBILITY_PUBLIC]);

    $this->actingAs(User::factory()->create())
        ->get(route('explore.index'))
        ->assertOk()
        ->assertSee('explore-public-visible')
        ->assertDontSee('explore-followers-hidden')
        ->assertDontSee('explore-private-hidden')
        ->assertDontSee('explore-private-account-hidden')
        ->assertDontSee('explore-banned-hidden');
});

it('search posts only shows publicly visible posts for authenticated users', function (): void {
    $author = User::factory()->create([
        'is_private' => false,
        'is_banned' => false,
    ]);

    Post::factory()->for($author)->create([
        'body' => 'search-visible-post',
        'visibility' => Post::VISIBILITY_PUBLIC,
        'status' => 'published',
    ]);

    Post::factory()->for($author)->create([
        'body' => 'search-private-post',
        'visibility' => Post::VISIBILITY_PRIVATE,
        'status' => 'published',
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('search.index', ['type' => 'posts', 'q' => 'search-']))
        ->assertOk()
        ->assertSee('search-visible-post')
        ->assertDontSee('search-private-post');
});

it('search users only shows discoverable users', function (): void {
    User::factory()->create([
        'name' => 'Search Public User',
        'username' => 'search_public_user',
        'is_private' => false,
        'is_banned' => false,
    ]);

    User::factory()->create([
        'name' => 'Search Private User',
        'username' => 'search_private_user',
        'is_private' => true,
        'is_banned' => false,
    ]);

    User::factory()->create([
        'name' => 'Search Banned User',
        'username' => 'search_banned_user',
        'is_private' => false,
        'is_banned' => true,
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('search.index', ['type' => 'users', 'q' => 'Search']))
        ->assertOk()
        ->assertSee('Search Public User')
        ->assertDontSee('Search Private User')
        ->assertDontSee('Search Banned User');
});
