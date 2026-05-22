<?php

use App\Enums\PostStatus;
use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Models\Social\Follow;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('owner profile shows posts visible to the owner through the profile timeline query', function (): void {
    $owner = User::factory()->create(['username' => 'owner_profile']);

    Post::factory()->for($owner)->create(['body' => 'owner-public-post', 'body_html' => '<p>owner-public-post</p>', 'visibility' => Post::VISIBILITY_PUBLIC]);
    Post::factory()->for($owner)->create(['body' => 'owner-followers-post', 'body_html' => '<p>owner-followers-post</p>', 'visibility' => Post::VISIBILITY_FOLLOWERS]);
    Post::factory()->for($owner)->create(['body' => 'owner-friends-post', 'body_html' => '<p>owner-friends-post</p>', 'visibility' => Post::VISIBILITY_FRIENDS]);
    Post::factory()->for($owner)->create(['body' => 'owner-private-post', 'body_html' => '<p>owner-private-post</p>', 'visibility' => Post::VISIBILITY_PRIVATE]);
    Post::factory()->for($owner)->create(['body' => 'owner-draft-post', 'body_html' => '<p>owner-draft-post</p>', 'status' => PostStatus::Draft->value, 'published_at' => null]);
    Post::factory()->for($owner)->create(['body' => 'owner-scheduled-post', 'body_html' => '<p>owner-scheduled-post</p>', 'status' => PostStatus::Scheduled->value, 'published_at' => now()->addDay()]);

    $this->actingAs($owner)
        ->get(route('profile.show', ['user' => $owner, 'tab' => 'posts']))
        ->assertOk()
        ->assertSee('owner-public-post')
        ->assertSee('owner-followers-post')
        ->assertSee('owner-friends-post')
        ->assertSee('owner-private-post')
        ->assertSee('owner-draft-post')
        ->assertSee('owner-scheduled-post')
        ->assertSee('data-post-status="scheduled"', false)
        ->assertSee('ring-amber-100', false)
        ->assertDontSee('Private posts')
        ->assertDontSee('Drafts &amp; Scheduled', false)
        ->assertSee('👥 Followers', false)
        ->assertSee('🤝 Friends', false)
        ->assertSee('🔒 Only me', false);
});

it('non-owner profile never shows private posts or visibility badges', function (): void {
    $owner = User::factory()->create(['username' => 'owner_profile_2', 'is_private' => false]);
    $viewer = User::factory()->create();

    Post::factory()->for($owner)->create(['body' => 'owner-2-public-post', 'body_html' => '<p>owner-2-public-post</p>', 'visibility' => Post::VISIBILITY_PUBLIC]);
    Post::factory()->for($owner)->create(['body' => 'owner-2-followers-post', 'body_html' => '<p>owner-2-followers-post</p>', 'visibility' => Post::VISIBILITY_FOLLOWERS]);
    Post::factory()->for($owner)->create(['body' => 'owner-2-friends-post', 'body_html' => '<p>owner-2-friends-post</p>', 'visibility' => Post::VISIBILITY_FRIENDS]);
    Post::factory()->for($owner)->create(['body' => 'owner-2-private-post', 'body_html' => '<p>owner-2-private-post</p>', 'visibility' => Post::VISIBILITY_PRIVATE]);

    $this->actingAs($viewer)
        ->get(route('profile.show', ['user' => $owner, 'tab' => 'posts']))
        ->assertOk()
        ->assertSee('owner-2-public-post')
        ->assertDontSee('owner-2-followers-post')
        ->assertDontSee('owner-2-friends-post')
        ->assertDontSee('owner-2-private-post')
        ->assertDontSee('👥 Followers', false)
        ->assertDontSee('🤝 Friends', false)
        ->assertDontSee('🔒 Only me', false);
});

it('accepted followers see follower-only but not friends-only posts from the lazy posts tab', function (): void {
    $owner = User::factory()->create(['username' => 'owner_profile_3', 'is_private' => false]);
    $viewer = User::factory()->create();

    Follow::query()->create([
        'follower_id' => $viewer->getKey(),
        'following_id' => $owner->getKey(),
        'status' => 'accepted',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Post::factory()->for($owner)->create(['body' => 'owner-3-public-post', 'body_html' => '<p>owner-3-public-post</p>', 'visibility' => Post::VISIBILITY_PUBLIC]);
    Post::factory()->for($owner)->create(['body' => 'owner-3-followers-post', 'body_html' => '<p>owner-3-followers-post</p>', 'visibility' => Post::VISIBILITY_FOLLOWERS]);
    Post::factory()->for($owner)->create(['body' => 'owner-3-friends-post', 'body_html' => '<p>owner-3-friends-post</p>', 'visibility' => Post::VISIBILITY_FRIENDS]);
    Post::factory()->for($owner)->create(['body' => 'owner-3-private-post', 'body_html' => '<p>owner-3-private-post</p>', 'visibility' => Post::VISIBILITY_PRIVATE]);

    $this->actingAs($viewer)
        ->get(route('profile.show', ['user' => $owner, 'tab' => 'posts']))
        ->assertOk()
        ->assertSee('owner-3-public-post')
        ->assertSee('owner-3-followers-post')
        ->assertDontSee('owner-3-friends-post')
        ->assertDontSee('owner-3-private-post')
        ->assertDontSee('🔒 Only me', false);
});

it('mutual followers see friends-only posts from the lazy posts tab', function (): void {
    $owner = User::factory()->create(['username' => 'owner_profile_4', 'is_private' => false]);
    $viewer = User::factory()->create();

    Follow::query()->create([
        'follower_id' => $viewer->getKey(),
        'following_id' => $owner->getKey(),
        'status' => 'accepted',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    Follow::query()->create([
        'follower_id' => $owner->getKey(),
        'following_id' => $viewer->getKey(),
        'status' => 'accepted',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Post::factory()->for($owner)->create(['body' => 'owner-4-public-post', 'body_html' => '<p>owner-4-public-post</p>', 'visibility' => Post::VISIBILITY_PUBLIC]);
    Post::factory()->for($owner)->create(['body' => 'owner-4-followers-post', 'body_html' => '<p>owner-4-followers-post</p>', 'visibility' => Post::VISIBILITY_FOLLOWERS]);
    Post::factory()->for($owner)->create(['body' => 'owner-4-friends-post', 'body_html' => '<p>owner-4-friends-post</p>', 'visibility' => Post::VISIBILITY_FRIENDS]);
    Post::factory()->for($owner)->create(['body' => 'owner-4-private-post', 'body_html' => '<p>owner-4-private-post</p>', 'visibility' => Post::VISIBILITY_PRIVATE]);

    $this->actingAs($viewer)
        ->get(route('profile.show', ['user' => $owner, 'tab' => 'posts']))
        ->assertOk()
        ->assertSee('owner-4-public-post')
        ->assertSee('owner-4-followers-post')
        ->assertSee('owner-4-friends-post')
        ->assertDontSee('owner-4-private-post');
});

it('private profile non-followers see the private profile message instead of posts', function (): void {
    $owner = User::factory()->create([
        'username' => 'owner_profile_5',
        'is_private' => true,
        'profile_visibility' => 'followers_only',
    ]);
    $viewer = User::factory()->create();

    Post::factory()->for($owner)->create(['body' => 'owner-5-public-post', 'body_html' => '<p>owner-5-public-post</p>', 'visibility' => Post::VISIBILITY_PUBLIC]);
    Post::factory()->for($owner)->create(['body' => 'owner-5-followers-post', 'body_html' => '<p>owner-5-followers-post</p>', 'visibility' => Post::VISIBILITY_FOLLOWERS]);

    $this->actingAs($viewer)
        ->get(route('profile.show', ['user' => $owner, 'tab' => 'posts']))
        ->assertOk()
        ->assertSee('This account is private')
        ->assertDontSee('owner-5-public-post')
        ->assertDontSee('owner-5-followers-post');
});
