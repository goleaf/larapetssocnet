<?php

use App\Models\Content\Comment;
use App\Models\Content\Post;
use App\Models\Identity\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('creates top-level comments from the Livewire thread and refreshes the parent card', function (): void {
    $author = User::factory()->create();
    $viewer = User::factory()->create();
    $post = Post::factory()->for($author)->create([
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);

    Livewire::actingAs($viewer)
        ->test('posts.comments-thread', ['post' => $post])
        ->set('body', 'Livewire thread comment')
        ->call('createComment')
        ->assertSet('body', '')
        ->assertSet('commentCount', 1)
        ->assertSee('Livewire thread comment')
        ->assertDispatched('post-card-refresh', postId: $post->id)
        ->assertDispatched('comments-thread-updated', postId: $post->id, commentsCount: 1);

    $this->assertDatabaseHas('comments', [
        'post_id' => $post->id,
        'user_id' => $viewer->id,
        'body' => 'Livewire thread comment',
    ]);

    $this->assertDatabaseHas('posts', [
        'id' => $post->id,
        'comments_count' => 1,
    ]);
});

it('creates replies from the Livewire thread and keeps deleted parents visible as tombstones', function (): void {
    $author = User::factory()->create();
    $viewer = User::factory()->create();
    $post = Post::factory()->for($author)->create([
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);
    $parent = Comment::factory()->for($post)->for($viewer, 'user')->create([
        'body' => 'Parent comment',
        'body_html' => 'Parent comment',
    ]);

    Livewire::actingAs($viewer)
        ->test('posts.comments-thread', ['post' => $post])
        ->set('replyBodies.'.$parent->id, 'Nested reply')
        ->call('createReply', $parent->id)
        ->assertSet('commentCount', 2)
        ->assertSee('Nested reply')
        ->call('deleteComment', $parent->id)
        ->assertSet('commentCount', 1)
        ->assertSee('This comment was removed.')
        ->assertSee('Nested reply');

    $this->assertSoftDeleted('comments', [
        'id' => $parent->id,
    ]);

    $this->assertDatabaseHas('comments', [
        'post_id' => $post->id,
        'parent_id' => $parent->id,
        'body' => 'Nested reply',
        'deleted_at' => null,
    ]);
});

it('rejects replies beyond the configured single reply level in the Livewire thread', function (): void {
    $author = User::factory()->create();
    $viewer = User::factory()->create();
    $post = Post::factory()->for($author)->create([
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);
    $parent = Comment::factory()->for($post)->for($author, 'user')->create([
        'body' => 'Parent comment',
        'body_html' => 'Parent comment',
    ]);
    $reply = Comment::factory()->for($post)->for($viewer, 'user')->create([
        'parent_id' => $parent->id,
        'body' => 'First-level reply',
        'body_html' => 'First-level reply',
    ]);

    Livewire::actingAs($viewer)
        ->test('posts.comments-thread', ['post' => $post])
        ->set('replyBodies.'.$reply->id, 'Too deep')
        ->call('createReply', $reply->id)
        ->assertHasErrors(['replyBodies.'.$reply->id])
        ->assertDontSee('Too deep');
});

it('refreshes the Livewire thread when another user adds a visible comment', function (): void {
    $author = User::factory()->create();
    $viewer = User::factory()->create();
    $post = Post::factory()->for($author)->create([
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);

    $component = Livewire::actingAs($viewer)
        ->test('posts.comments-thread', ['post' => $post])
        ->assertSet('commentCount', 0);

    Comment::factory()->for($post)->for($author, 'user')->create([
        'body' => 'External comment',
        'body_html' => 'External comment',
    ]);

    $component
        ->call('refreshThread')
        ->assertSet('commentCount', 1)
        ->assertSet('hasFreshActivity', true)
        ->assertSee('External comment')
        ->assertDispatched('post-card-refresh', postId: $post->id);
});
