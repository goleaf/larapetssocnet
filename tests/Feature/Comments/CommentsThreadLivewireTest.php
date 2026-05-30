<?php

use App\Models\Content\Comment;
use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Models\Moderation\Report;
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

it('allows reply to reply comments and flattens deeper replies in the Livewire thread', function (): void {
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

    $component = Livewire::actingAs($viewer)
        ->test('posts.comments-thread', ['post' => $post])
        ->set('replyBodies.'.$reply->id, 'Second-level reply')
        ->call('createReply', $reply->id)
        ->assertHasNoErrors()
        ->assertSee('Second-level reply');

    $thirdLevel = Comment::query()
        ->where('post_id', $post->id)
        ->where('parent_id', $reply->id)
        ->where('body', 'Second-level reply')
        ->firstOrFail();

    $component
        ->set('replyBodies.'.$thirdLevel->id, 'Flattened reply')
        ->call('createReply', $thirdLevel->id)
        ->assertHasNoErrors()
        ->assertSee('Flattened reply');

    $this->assertDatabaseHas('comments', [
        'post_id' => $post->id,
        'parent_id' => $reply->id,
        'body' => 'Flattened reply',
    ]);
});

it('edits comments inline from the Livewire thread and keeps sanitized output in sync', function (): void {
    $author = User::factory()->create();
    $viewer = User::factory()->create();
    $post = Post::factory()->for($author)->create([
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);
    $comment = Comment::factory()->for($post)->for($viewer, 'user')->create([
        'body' => 'Original comment',
        'body_html' => 'Original comment',
    ]);

    Livewire::actingAs($viewer)
        ->test('posts.comments-thread', ['post' => $post])
        ->call('startEditing', $comment->id)
        ->assertSet('editingCommentId', $comment->id)
        ->assertSet('editBodies.'.$comment->id, 'Original comment')
        ->set('editBodies.'.$comment->id, '<script>alert("x")</script> Edited with 🐾')
        ->call('updateComment', $comment->id)
        ->assertSet('editingCommentId', null)
        ->assertSee('Comment updated.')
        ->assertSee('Edited with 🐾')
        ->assertDontSee('<script>alert("x")</script>', false)
        ->assertDispatched('post-card-refresh', postId: $post->id)
        ->assertDispatched('comments-thread-updated', postId: $post->id, commentsCount: 1);

    $comment->refresh();

    expect($comment->body)->toBe('<script>alert("x")</script> Edited with 🐾')
        ->and($comment->body_html)->not->toContain('<script')
        ->and($comment->edited_at)->not->toBeNull();
});

it('reports comments inline from the Livewire thread', function (): void {
    $reporter = User::factory()->create();
    $author = User::factory()->create();
    $post = Post::factory()->for($author)->create([
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);
    $comment = Comment::factory()->for($post)->for($author, 'user')->create([
        'body' => 'Reportable comment',
        'body_html' => 'Reportable comment',
    ]);

    Livewire::actingAs($reporter)
        ->test('posts.comments-thread', ['post' => $post])
        ->call('openReport', $comment->id)
        ->assertSet('reportingCommentId', $comment->id)
        ->set('reportReason', Report::REASON_MISINFORMATION)
        ->set('reportDetails', 'This comment needs a moderator review.')
        ->call('reportComment')
        ->assertSee('Comment reported. Thank you.')
        ->assertDispatched('comments-thread-reported', postId: $post->id, commentId: $comment->id);

    $this->assertDatabaseHas('reports', [
        'reporter_user_id' => $reporter->id,
        'reportable_type' => (new Comment)->getMorphClass(),
        'reportable_id' => $comment->id,
        'reason' => Report::REASON_MISINFORMATION,
        'status' => Report::STATUS_PENDING,
    ]);
});

it('blocks inline self-reporting from the Livewire thread', function (): void {
    $author = User::factory()->create();
    $post = Post::factory()->for($author)->create([
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);
    $comment = Comment::factory()->for($post)->for($author, 'user')->create([
        'body' => 'Own comment',
        'body_html' => 'Own comment',
    ]);

    Livewire::actingAs($author)
        ->test('posts.comments-thread', ['post' => $post])
        ->call('openReport', $comment->id)
        ->assertForbidden();

    expect(Report::query()->count())->toBe(0);
});

it('loads a three-comment preview and can append older top-level comments', function (): void {
    $author = User::factory()->create();
    $viewer = User::factory()->create();
    $post = Post::factory()->for($author)->create([
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);

    Comment::factory()->for($post)->for($author, 'user')->create([
        'body' => 'Oldest top-level comment',
        'body_html' => 'Oldest top-level comment',
        'created_at' => now()->subMinutes(4),
    ]);
    Comment::factory()->for($post)->for($author, 'user')->create([
        'body' => 'Third newest top-level comment',
        'body_html' => 'Third newest top-level comment',
        'created_at' => now()->subMinutes(3),
    ]);
    Comment::factory()->for($post)->for($author, 'user')->create([
        'body' => 'Second newest top-level comment',
        'body_html' => 'Second newest top-level comment',
        'created_at' => now()->subMinutes(2),
    ]);
    Comment::factory()->for($post)->for($author, 'user')->create([
        'body' => 'Newest top-level comment',
        'body_html' => 'Newest top-level comment',
        'created_at' => now()->subMinute(),
    ]);

    Livewire::actingAs($viewer)
        ->test('posts.comments-thread', ['post' => $post])
        ->assertSee('Newest top-level comment')
        ->assertSee('Second newest top-level comment')
        ->assertSee('Third newest top-level comment')
        ->assertDontSee('Oldest top-level comment')
        ->call('loadMoreComments')
        ->assertSee('Oldest top-level comment');
});

it('shows two recent replies until a thread is expanded', function (): void {
    $author = User::factory()->create();
    $viewer = User::factory()->create();
    $post = Post::factory()->for($author)->create([
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);
    $parent = Comment::factory()->for($post)->for($author, 'user')->create([
        'body' => 'Parent with replies',
        'body_html' => 'Parent with replies',
    ]);

    Comment::factory()->for($post)->for($viewer, 'user')->create([
        'parent_id' => $parent->id,
        'body' => 'Oldest reply',
        'body_html' => 'Oldest reply',
        'created_at' => now()->subMinutes(3),
    ]);
    Comment::factory()->for($post)->for($viewer, 'user')->create([
        'parent_id' => $parent->id,
        'body' => 'Middle reply',
        'body_html' => 'Middle reply',
        'created_at' => now()->subMinutes(2),
    ]);
    Comment::factory()->for($post)->for($viewer, 'user')->create([
        'parent_id' => $parent->id,
        'body' => 'Newest reply',
        'body_html' => 'Newest reply',
        'created_at' => now()->subMinute(),
    ]);

    Livewire::actingAs($viewer)
        ->test('posts.comments-thread', ['post' => $post])
        ->assertSee('Newest reply')
        ->assertSee('Middle reply')
        ->assertDontSee('Oldest reply')
        ->call('toggleReplies', $parent->id)
        ->assertSee('Oldest reply');
});

it('pins and unpins one comment through the Livewire thread', function (): void {
    $author = User::factory()->create();
    $post = Post::factory()->for($author)->create([
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);
    $comment = Comment::factory()->for($post)->for($author, 'user')->create([
        'body' => 'Pin-worthy comment',
        'body_html' => 'Pin-worthy comment',
    ]);

    Livewire::actingAs($author)
        ->test('posts.comments-thread', ['post' => $post])
        ->call('pinComment', $comment->id)
        ->assertSet('pinnedCommentId', $comment->id)
        ->assertSee('Comment pinned.')
        ->assertSee('Pinned')
        ->call('unpinComment')
        ->assertSet('pinnedCommentId', null)
        ->assertSee('Comment unpinned.');

    expect($post->fresh()->metadata)->not->toHaveKey('pinned_comment_id');
});

it('blocks a commenter from the Livewire thread and hides their comments', function (): void {
    $viewer = User::factory()->create();
    $blockedAuthor = User::factory()->create([
        'username' => 'blocked-commenter',
    ]);
    $post = Post::factory()->for(User::factory())->create([
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);
    $comment = Comment::factory()->for($post)->for($blockedAuthor, 'user')->create([
        'body' => 'Blocked user visible before block',
        'body_html' => 'Blocked user visible before block',
    ]);

    Livewire::actingAs($viewer)
        ->test('posts.comments-thread', ['post' => $post])
        ->assertSee('Blocked user visible before block')
        ->call('blockCommenter', $comment->id)
        ->assertSee('@blocked-commenter has been blocked.')
        ->assertDontSee('Blocked user visible before block');

    $this->assertDatabaseHas('blocks', [
        'blocker_id' => $viewer->id,
        'blocked_id' => $blockedAuthor->id,
    ]);
});

it('rejects comments longer than five hundred characters in the Livewire thread', function (): void {
    $author = User::factory()->create();
    $viewer = User::factory()->create();
    $post = Post::factory()->for($author)->create([
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);

    Livewire::actingAs($viewer)
        ->test('posts.comments-thread', ['post' => $post])
        ->set('body', str_repeat('a', 501))
        ->call('createComment')
        ->assertHasErrors(['body'])
        ->assertSee('Comments may not be longer than 500 characters.');
});

it('prevents inline editing after the one hour edit window closes', function (): void {
    $author = User::factory()->create();
    $post = Post::factory()->for($author)->create([
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);
    $comment = Comment::factory()->for($post)->for($author, 'user')->create([
        'body' => 'Older editable comment',
        'body_html' => 'Older editable comment',
        'created_at' => now()->subMinutes(61),
    ]);

    Livewire::actingAs($author)
        ->test('posts.comments-thread', ['post' => $post])
        ->call('startEditing', $comment->id)
        ->assertForbidden();
});

it('sorts the full post page comment thread without leaving Livewire', function (): void {
    $author = User::factory()->create();
    $viewer = User::factory()->create();
    $post = Post::factory()->for($author)->create([
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);

    Comment::factory()->for($post)->for($author, 'user')->create([
        'body' => 'Old quiet comment',
        'body_html' => 'Old quiet comment',
        'reactions_count' => 0,
        'created_at' => now()->subMinutes(10),
    ]);
    Comment::factory()->for($post)->for($viewer, 'user')->create([
        'body' => 'Loved comment',
        'body_html' => 'Loved comment',
        'reactions_count' => 9,
        'created_at' => now()->subMinutes(5),
    ]);

    Livewire::actingAs($viewer)
        ->test('posts.comments-thread', ['post' => $post, 'fullPage' => true])
        ->assertSee('Top')
        ->assertSeeInOrder(['Old quiet comment', 'Loved comment'])
        ->call('setSort', 'top')
        ->assertSet('sort', 'top')
        ->assertSeeInOrder(['Loved comment', 'Old quiet comment']);
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
