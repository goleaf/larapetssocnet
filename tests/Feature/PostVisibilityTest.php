<?php

use App\Enums\PostStatus;
use App\Models\Content\Comment;
use App\Models\Content\Post;
use App\Models\Identity\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('direct url access returns 403 for denied visibility and 200 for allowed', function (): void {
    $author = User::factory()->create();
    $follower = User::factory()->create();
    $stranger = User::factory()->create();
    $follower->follow($author);
    $author->approveFollowRequest($follower);

    $followersPost = Post::factory()->for($author)->create(['visibility' => Post::VISIBILITY_FOLLOWERS]);
    $privatePost = Post::factory()->for($author)->create(['visibility' => Post::VISIBILITY_PRIVATE]);

    $this->get(route('posts.show', $followersPost))->assertForbidden();
    $this->actingAs($stranger)->get(route('posts.show', $followersPost))->assertForbidden();
    $this->actingAs($follower)->get(route('posts.show', $followersPost))->assertOk();
    $this->actingAs($author)->get(route('posts.show', $privatePost))->assertOk();
    $this->actingAs($stranger)->get(route('posts.show', $privatePost))->assertForbidden();
});

it('scheduled posts are hidden before publish time', function (): void {
    $author = User::factory()->create();
    $viewer = User::factory()->create();
    $viewer->follow($author);
    $author->approveFollowRequest($viewer);

    $scheduled = Post::factory()->for($author)->create([
        'status' => PostStatus::Scheduled->value,
        'published_at' => now()->addHour(),
        'visibility' => Post::VISIBILITY_FOLLOWERS,
    ]);

    $this->actingAs($viewer)
        ->get(route('posts.show', $scheduled))
        ->assertForbidden();
});

it('draft posts are only visible to the owner', function (): void {
    $author = User::factory()->create();
    $other = User::factory()->create();

    $draft = Post::factory()->for($author)->create([
        'status' => PostStatus::Draft->value,
        'published_at' => null,
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);

    $this->actingAs($author)->get(route('posts.show', $draft))->assertOk();
    $this->actingAs($other)->get(route('posts.show', $draft))->assertForbidden();
});

it('blocked users cannot access public posts', function (): void {
    $author = User::factory()->create();
    $blocked = User::factory()->create();

    $author->block($blocked);

    $post = Post::factory()->for($author)->create([
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);

    $this->actingAs($blocked)
        ->get(route('posts.show', $post))
        ->assertForbidden();
});

it('post create accepts valid visibilities and defaults to public', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('posts.store'), [
        'body' => 'public post',
        'visibility' => 'public',
    ])->assertRedirect();

    $this->actingAs($user)->post(route('posts.store'), [
        'body' => 'followers post',
        'visibility' => 'followers',
    ])->assertRedirect();

    $this->actingAs($user)->post(route('posts.store'), [
        'body' => 'private post',
        'visibility' => 'private',
    ])->assertRedirect();

    $this->actingAs($user)->post(route('posts.store'), [
        'body' => 'default visibility post',
    ])->assertRedirect();

    expect(Post::query()->where('body', 'default visibility post')->firstOrFail()->visibility)->toBe('public');
});

it('post create rejects invalid visibility', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->from(route('posts.create'))->post(route('posts.store'), [
        'body' => 'invalid visibility',
        'visibility' => 'friends-only',
    ])->assertSessionHasErrors(['visibility']);
});

it('visibility change preserves interaction counters', function (): void {
    $user = User::factory()->create();
    $post = Post::factory()->for($user)->create([
        'visibility' => Post::VISIBILITY_PUBLIC,
        'likes_count' => 3,
        'comments_count' => 0,
    ]);

    Comment::factory()->count(2)->for($post)->for($user)->create();

    $this->actingAs($user)->patch(route('posts.update', $post), [
        'body' => $post->body,
        'visibility' => Post::VISIBILITY_PRIVATE,
    ])->assertRedirect();

    $post->refresh();

    expect($post->likes_count)->toBe(3);
    expect($post->comments_count)->toBe(2);

    $this->actingAs($user)->patch(route('posts.update', $post), [
        'body' => $post->body,
        'visibility' => Post::VISIBILITY_PUBLIC,
    ])->assertRedirect();

    expect($post->fresh()->visibility)->toBe(Post::VISIBILITY_PUBLIC);
});

it('edit form includes downgrade warning block for engaged posts', function (): void {
    $user = User::factory()->create();
    $post = Post::factory()->for($user)->create([
        'visibility' => Post::VISIBILITY_PUBLIC,
        'likes_count' => 2,
        'comments_count' => 1,
    ]);

    $this->actingAs($user)
        ->get(route('posts.edit', $post))
        ->assertOk()
        ->assertSee('Restricting visibility will hide it from people who already engaged with it.');
});
