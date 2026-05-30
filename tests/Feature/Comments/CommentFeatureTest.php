<?php

use App\Models\Content\Comment;
use App\Models\Content\Post;
use App\Models\Identity\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows an authorized user to add a top-level comment', function (): void {
    $author = User::factory()->create();
    $viewer = User::factory()->create();
    $post = Post::factory()->for($author)->create([
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);

    $this->actingAs($viewer)
        ->post(route('posts.comments.store', $post), [
            'body' => 'First!',
        ])
        ->assertRedirect();

    $comment = Comment::query()->firstOrFail();

    expect($comment->body)->toBe('First!');
    expect($comment->body_html)->not->toBeNull();

    $this->assertDatabaseHas('posts', [
        'id' => $post->id,
        'comments_count' => 1,
    ]);
});

it('redirects guests who try to post a comment to login', function (): void {
    $author = User::factory()->create();
    $post = Post::factory()->for($author)->create([
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);

    $this->post(route('posts.comments.store', $post), [
        'body' => 'Guest comment attempt',
    ])->assertRedirect(route('login'));

    $this->assertDatabaseMissing('comments', [
        'post_id' => $post->id,
        'body' => 'Guest comment attempt',
    ]);

    $this->assertDatabaseHas('posts', [
        'id' => $post->id,
        'comments_count' => 0,
    ]);
});

it('allows replies and keeps reply counters in sync', function (): void {
    $author = User::factory()->create();
    $viewer = User::factory()->create();
    $post = Post::factory()->for($author)->create([
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);

    $this->actingAs($viewer)
        ->post(route('posts.comments.store', $post), ['body' => 'Top level'])
        ->assertRedirect();

    $parent = Comment::query()->firstOrFail();

    $this->actingAs($viewer)
        ->post(route('posts.comments.store', $post), [
            'body' => 'Reply',
            'parent_id' => $parent->id,
        ])
        ->assertRedirect();

    $parent->refresh();

    expect($parent->replies_count)->toBe(1);
    $this->assertDatabaseHas('posts', [
        'id' => $post->id,
        'comments_count' => 2,
    ]);
});

it('allows nested replies and flattens replies beyond the third visual level', function (): void {
    $author = User::factory()->create();
    $viewer = User::factory()->create();
    $post = Post::factory()->for($author)->create();

    $top = Comment::query()->create([
        'post_id' => $post->id,
        'user_id' => $author->id,
        'body' => 'Top',
        'body_html' => 'Top',
    ]);

    $reply = Comment::query()->create([
        'post_id' => $post->id,
        'user_id' => $author->id,
        'parent_id' => $top->id,
        'body' => 'Reply',
        'body_html' => 'Reply',
    ]);

    $this->actingAs($viewer)
        ->from(route('posts.show', $post))
        ->post(route('posts.comments.store', $post), [
            'body' => 'Second reply level',
            'parent_id' => $reply->id,
        ])
        ->assertRedirect(route('posts.show', $post))
        ->assertSessionDoesntHaveErrors();

    $thirdLevel = Comment::query()
        ->where('post_id', $post->id)
        ->where('parent_id', $reply->id)
        ->where('body', 'Second reply level')
        ->firstOrFail();

    $this->actingAs($viewer)
        ->from(route('posts.show', $post))
        ->post(route('posts.comments.store', $post), [
            'body' => 'Flattened reply',
            'parent_id' => $thirdLevel->id,
        ])
        ->assertRedirect(route('posts.show', $post))
        ->assertSessionDoesntHaveErrors();

    $this->assertDatabaseHas('comments', [
        'post_id' => $post->id,
        'parent_id' => $reply->id,
        'body' => 'Flattened reply',
    ]);
});

it('prevents commenting on an inaccessible post', function (): void {
    $author = User::factory()->create(['is_private' => true]);
    $viewer = User::factory()->create();
    $post = Post::factory()->for($author)->create([
        'visibility' => Post::VISIBILITY_PRIVATE,
    ]);

    $this->actingAs($viewer)
        ->post(route('posts.comments.store', $post), [
            'body' => 'Should not work',
        ])
        ->assertForbidden();
});

it('prevents blocked users from commenting', function (): void {
    $author = User::factory()->create();
    $viewer = User::factory()->create();
    $post = Post::factory()->for($author)->create([
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);

    $author->blocking()->attach($viewer->id);

    $this->actingAs($viewer)
        ->post(route('posts.comments.store', $post), [
            'body' => 'Nope',
        ])
        ->assertForbidden();
});

it('hides comments from a blocked user when the blocker views the thread', function (): void {
    $author = User::factory()->create();
    $blocker = User::factory()->create();
    $blocked = User::factory()->create();
    $post = Post::factory()->for($author)->create([
        'body' => 'Post visible to the blocker',
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);

    Comment::factory()->for($post)->for($blocked, 'user')->create([
        'body' => 'blocked-user-comment-body',
        'body_html' => 'blocked-user-comment-body',
    ]);

    $blocker->blocking()->attach($blocked->id);

    $this->actingAs($blocked)
        ->get(route('posts.show', $post))
        ->assertOk()
        ->assertSee('blocked-user-comment-body');

    $this->actingAs($blocker)
        ->get(route('posts.show', $post))
        ->assertOk()
        ->assertSee('Post visible to the blocker')
        ->assertDontSee('blocked-user-comment-body');
});

it('updates edited_at only when content changes', function (): void {
    $author = User::factory()->create();
    $post = Post::factory()->for($author)->create();

    $comment = Comment::query()->create([
        'post_id' => $post->id,
        'user_id' => $author->id,
        'body' => 'Original',
        'body_html' => 'Original',
    ]);

    $this->actingAs($author)
        ->patch(route('posts.comments.update', [$post, $comment]), [
            'body' => 'Original',
        ])
        ->assertRedirect();

    $comment->refresh();
    expect($comment->edited_at)->toBeNull();

    $this->actingAs($author)
        ->patch(route('posts.comments.update', [$post, $comment]), [
            'body' => 'Updated',
        ])
        ->assertRedirect();

    $comment->refresh();
    expect($comment->edited_at)->not->toBeNull();
});

it('enforces ownership on edit and delete', function (): void {
    $author = User::factory()->create();
    $stranger = User::factory()->create();
    $post = Post::factory()->for($author)->create();

    $comment = Comment::query()->create([
        'post_id' => $post->id,
        'user_id' => $author->id,
        'body' => 'Protected',
        'body_html' => 'Protected',
    ]);

    $this->actingAs($stranger)
        ->patch(route('posts.comments.update', [$post, $comment]), [
            'body' => 'Hack',
        ])
        ->assertForbidden();

    $this->actingAs($stranger)
        ->delete(route('posts.comments.destroy', [$post, $comment]))
        ->assertForbidden();
});

it('keeps replies when deleting a parent comment', function (): void {
    $author = User::factory()->create();
    $post = Post::factory()->for($author)->create();

    $parent = Comment::query()->create([
        'post_id' => $post->id,
        'user_id' => $author->id,
        'body' => 'Parent',
        'body_html' => 'Parent',
    ]);

    $reply = Comment::query()->create([
        'post_id' => $post->id,
        'user_id' => $author->id,
        'parent_id' => $parent->id,
        'body' => 'Reply',
        'body_html' => 'Reply',
    ]);

    $this->actingAs($author)
        ->delete(route('posts.comments.destroy', [$post, $parent]))
        ->assertRedirect();

    $this->assertSoftDeleted('comments', [
        'id' => $parent->id,
    ]);

    $this->assertDatabaseHas('comments', [
        'id' => $reply->id,
        'deleted_at' => null,
    ]);

    $this->assertDatabaseHas('posts', [
        'id' => $post->id,
        'comments_count' => 1,
    ]);
});

it('rejects replies to comments from another post', function (): void {
    $author = User::factory()->create();
    $viewer = User::factory()->create();

    $postA = Post::factory()->for($author)->create();
    $postB = Post::factory()->for($author)->create();

    $commentA = Comment::query()->create([
        'post_id' => $postA->id,
        'user_id' => $author->id,
        'body' => 'Comment A',
        'body_html' => 'Comment A',
    ]);

    $this->actingAs($viewer)
        ->from(route('posts.show', $postB))
        ->post(route('posts.comments.store', $postB), [
            'body' => 'Cross post',
            'parent_id' => $commentA->id,
        ])
        ->assertRedirect(route('posts.show', $postB))
        ->assertSessionHasErrors('parent_id');
});

it('restores counters when a reply is restored', function (): void {
    $author = User::factory()->create();
    $post = Post::factory()->for($author)->create();

    $parent = Comment::query()->create([
        'post_id' => $post->id,
        'user_id' => $author->id,
        'body' => 'Parent',
        'body_html' => 'Parent',
    ]);

    $reply = Comment::query()->create([
        'post_id' => $post->id,
        'user_id' => $author->id,
        'parent_id' => $parent->id,
        'body' => 'Reply',
        'body_html' => 'Reply',
    ]);

    $this->actingAs($author)
        ->delete(route('posts.comments.destroy', [$post, $reply]))
        ->assertRedirect();

    $parent->refresh();
    expect($parent->replies_count)->toBe(0);

    $reply->restore();

    $parent->refresh();
    $post->refresh();

    expect($parent->replies_count)->toBe(1);
    expect($post->comments_count)->toBe(2);
});
