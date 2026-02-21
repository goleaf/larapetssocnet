<?php

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('explore only shows public posts from public and non-banned authors', function (): void {
    $publicAuthor = User::factory()->create(['is_private' => false, 'is_banned' => false]);
    $privateAuthor = User::factory()->create(['is_private' => true, 'is_banned' => false]);
    $bannedAuthor = User::factory()->create(['is_private' => false, 'is_banned' => true]);

    Post::factory()->for($publicAuthor)->create(['body' => 'explore-public-visible', 'visibility' => Post::VISIBILITY_PUBLIC]);
    Post::factory()->for($publicAuthor)->create(['body' => 'explore-followers-hidden', 'visibility' => Post::VISIBILITY_FOLLOWERS]);
    Post::factory()->for($publicAuthor)->create(['body' => 'explore-private-hidden', 'visibility' => Post::VISIBILITY_PRIVATE]);
    Post::factory()->for($privateAuthor)->create(['body' => 'explore-private-account-hidden', 'visibility' => Post::VISIBILITY_PUBLIC]);
    Post::factory()->for($bannedAuthor)->create(['body' => 'explore-banned-hidden', 'visibility' => Post::VISIBILITY_PUBLIC]);

    $this->get(route('explore.index'))
        ->assertOk()
        ->assertSee('explore-public-visible')
        ->assertDontSee('explore-followers-hidden')
        ->assertDontSee('explore-private-hidden')
        ->assertDontSee('explore-private-account-hidden')
        ->assertDontSee('explore-banned-hidden');
});
