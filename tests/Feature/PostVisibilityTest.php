<?php

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

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
        'comments_count' => 2,
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
