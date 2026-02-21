<?php

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

function acceptedFollow(User $follower, User $author): void
{
    $follower->follow($author);
    $author->approveFollowRequest($follower);
}

it('public post from public account visibility matrix works', function (): void {
    $author = User::factory()->create(['is_private' => false]);
    $follower = User::factory()->create();
    $nonFollower = User::factory()->create();
    $post = Post::factory()->for($author)->create(['visibility' => Post::VISIBILITY_PUBLIC, 'body' => 'public-public-matrix']);

    acceptedFollow($follower, $author);

    $this->get(route('posts.show', $post))->assertOk();
    $this->actingAs($nonFollower)->get(route('posts.show', $post))->assertOk();
    $this->actingAs($follower)->get(route('posts.show', $post))->assertOk();
    $this->actingAs($author)->get(route('posts.show', $post))->assertOk();
});

it('public post from private account is visible only to follower and author', function (): void {
    $author = User::factory()->create(['is_private' => true]);
    $follower = User::factory()->create();
    $nonFollower = User::factory()->create();
    $post = Post::factory()->for($author)->create(['visibility' => Post::VISIBILITY_PUBLIC]);

    acceptedFollow($follower, $author);

    $this->get(route('posts.show', $post))->assertForbidden();
    $this->actingAs($nonFollower)->get(route('posts.show', $post))->assertForbidden();
    $this->actingAs($follower)->get(route('posts.show', $post))->assertOk();
    $this->actingAs($author)->get(route('posts.show', $post))->assertOk();
});

it('followers visibility is only visible to accepted follower and author', function (): void {
    $author = User::factory()->create(['is_private' => false]);
    $follower = User::factory()->create();
    $nonFollower = User::factory()->create();
    $post = Post::factory()->for($author)->create(['visibility' => Post::VISIBILITY_FOLLOWERS]);

    acceptedFollow($follower, $author);

    $this->get(route('posts.show', $post))->assertForbidden();
    $this->actingAs($nonFollower)->get(route('posts.show', $post))->assertForbidden();
    $this->actingAs($follower)->get(route('posts.show', $post))->assertOk();
    $this->actingAs($author)->get(route('posts.show', $post))->assertOk();
});

it('private visibility is only visible to author', function (): void {
    $author = User::factory()->create();
    $follower = User::factory()->create();
    $nonFollower = User::factory()->create();
    $post = Post::factory()->for($author)->create(['visibility' => Post::VISIBILITY_PRIVATE]);

    acceptedFollow($follower, $author);

    $this->get(route('posts.show', $post))->assertForbidden();
    $this->actingAs($nonFollower)->get(route('posts.show', $post))->assertForbidden();
    $this->actingAs($follower)->get(route('posts.show', $post))->assertForbidden();
    $this->actingAs($author)->get(route('posts.show', $post))->assertOk();
});

it('admin and moderator can view all non-banned posts', function (): void {
    Role::findOrCreate('admin');
    Role::findOrCreate('moderator');

    $author = User::factory()->create();
    $admin = User::factory()->create();
    $moderator = User::factory()->create();
    $admin->assignRole('admin');
    $moderator->assignRole('moderator');

    $public = Post::factory()->for($author)->create(['visibility' => Post::VISIBILITY_PUBLIC]);
    $followers = Post::factory()->for($author)->create(['visibility' => Post::VISIBILITY_FOLLOWERS]);
    $private = Post::factory()->for($author)->create(['visibility' => Post::VISIBILITY_PRIVATE]);

    $this->actingAs($admin)->get(route('posts.show', $public))->assertOk();
    $this->actingAs($admin)->get(route('posts.show', $followers))->assertOk();
    $this->actingAs($admin)->get(route('posts.show', $private))->assertOk();
    $this->actingAs($moderator)->get(route('posts.show', $followers))->assertOk();
    $this->actingAs($moderator)->get(route('posts.show', $private))->assertOk();
});

it('block relationship overrides post visibility', function (): void {
    $author = User::factory()->create(['is_private' => false]);
    $viewer = User::factory()->create();

    acceptedFollow($viewer, $author);
    $public = Post::factory()->for($author)->create(['visibility' => Post::VISIBILITY_PUBLIC]);
    $followers = Post::factory()->for($author)->create(['visibility' => Post::VISIBILITY_FOLLOWERS]);

    $viewer->block($author);

    $this->actingAs($viewer)->get(route('posts.show', $public))->assertForbidden();
    $this->actingAs($viewer)->get(route('posts.show', $followers))->assertForbidden();
});
