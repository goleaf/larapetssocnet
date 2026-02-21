<?php

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('owner profile shows private posts section with badges', function (): void {
    $owner = User::factory()->create(['username' => 'owner_profile']);

    Post::factory()->for($owner)->create(['body' => 'owner-public-post', 'body_html' => '<p>owner-public-post</p>', 'visibility' => Post::VISIBILITY_PUBLIC]);
    Post::factory()->for($owner)->create(['body' => 'owner-followers-post', 'body_html' => '<p>owner-followers-post</p>', 'visibility' => Post::VISIBILITY_FOLLOWERS]);
    Post::factory()->for($owner)->create(['body' => 'owner-private-post', 'body_html' => '<p>owner-private-post</p>', 'visibility' => Post::VISIBILITY_PRIVATE]);

    $this->actingAs($owner)
        ->get(route('profile.show', ['user' => $owner, 'tab' => 'posts']))
        ->assertOk()
        ->assertSee('owner-public-post')
        ->assertSee('owner-followers-post')
        ->assertSee('owner-private-post')
        ->assertSee('Private posts')
        ->assertSee('👥 Followers', false)
        ->assertSee('🔒 Only me', false);
});

it('non-owner profile never shows private posts or visibility badges', function (): void {
    $owner = User::factory()->create(['username' => 'owner_profile_2', 'is_private' => false]);
    $viewer = User::factory()->create();

    Post::factory()->for($owner)->create(['body' => 'owner-2-public-post', 'body_html' => '<p>owner-2-public-post</p>', 'visibility' => Post::VISIBILITY_PUBLIC]);
    Post::factory()->for($owner)->create(['body' => 'owner-2-followers-post', 'body_html' => '<p>owner-2-followers-post</p>', 'visibility' => Post::VISIBILITY_FOLLOWERS]);
    Post::factory()->for($owner)->create(['body' => 'owner-2-private-post', 'body_html' => '<p>owner-2-private-post</p>', 'visibility' => Post::VISIBILITY_PRIVATE]);

    $this->actingAs($viewer)
        ->get(route('profile.show', ['user' => $owner, 'tab' => 'posts']))
        ->assertOk()
        ->assertSee('owner-2-public-post')
        ->assertDontSee('owner-2-followers-post')
        ->assertDontSee('owner-2-private-post')
        ->assertDontSee('👥 Followers', false)
        ->assertDontSee('🔒 Only me', false);
});
