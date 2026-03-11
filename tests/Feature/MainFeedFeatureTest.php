<?php

use App\Models\Comment;
use App\Models\Follow;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('shows followed posts and then an empty feed when user follows nobody', function () {
    $viewer = User::factory()->create([
        'is_private' => false,
        'is_banned' => false,
    ]);
    $followed = User::factory()->create([
        'is_private' => false,
        'is_banned' => false,
    ]);
    $stranger = User::factory()->create([
        'is_private' => false,
        'is_banned' => false,
    ]);

    Post::factory()->for($followed)->create([
        'body' => 'Followed pet post',
        'body_html' => '<p>Followed pet post</p>',
    ]);
    Post::factory()->for($stranger)->create([
        'body' => 'Discovery pet post',
        'body_html' => '<p>Discovery pet post</p>',
    ]);

    Follow::query()->create([
        'follower_id' => $viewer->id,
        'following_id' => $followed->id,
        'status' => 'accepted',
        'created_at' => now(),
    ]);

    $this->actingAs($viewer)
        ->get(route('feed.index'))
        ->assertSuccessful()
        ->assertSee('Followed pet post')
        ->assertDontSee('Discovery pet post');

    Follow::query()
        ->where('follower_id', $viewer->id)
        ->where('following_id', $followed->id)
        ->delete();

    $this->actingAs($viewer)
        ->get(route('feed.index'))
        ->assertSuccessful()
        ->assertDontSee('Followed pet post')
        ->assertDontSee('Discovery pet post')
        ->assertSee('Follow some pets to see posts');
});

it('toggles post likes via json endpoint', function () {
    $viewer = User::factory()->create([
        'is_private' => false,
        'is_banned' => false,
    ]);
    $author = User::factory()->create([
        'is_private' => false,
        'is_banned' => false,
    ]);
    $post = Post::factory()->for($author)->create();

    $this->actingAs($viewer)
        ->postJson(route('posts.like', $post))
        ->assertSuccessful()
        ->assertJson([
            'liked' => true,
            'count' => 1,
        ]);

    $this->assertDatabaseHas('likes', [
        'user_id' => $viewer->id,
        'post_id' => $post->id,
    ]);

    $this->actingAs($viewer)
        ->postJson(route('posts.like', $post))
        ->assertSuccessful()
        ->assertJson([
            'liked' => false,
            'count' => 0,
        ]);

    $this->assertDatabaseMissing('likes', [
        'user_id' => $viewer->id,
        'post_id' => $post->id,
    ]);
});

it('stores and deletes comments through main feed endpoints', function () {
    $viewer = User::factory()->create([
        'is_private' => false,
        'is_banned' => false,
    ]);
    $author = User::factory()->create([
        'is_private' => false,
        'is_banned' => false,
    ]);
    $post = Post::factory()->for($author)->create();

    $this->actingAs($viewer)
        ->from(route('feed.index'))
        ->post(route('posts.comments.store', $post), [
            'body' => 'This is a lovely pet update.',
        ])
        ->assertRedirect(route('feed.index'));

    $comment = Comment::query()->latest('id')->firstOrFail();
    expect($comment->body)->toBe('This is a lovely pet update.');

    $this->actingAs($viewer)
        ->from(route('feed.index'))
        ->delete(route('comments.destroy', $comment))
        ->assertRedirect(route('feed.index'));

    $this->assertSoftDeleted('comments', [
        'id' => $comment->id,
    ]);
});

it('toggles follow and unfollow through the main follow endpoint', function () {
    $viewer = User::factory()->create([
        'is_private' => false,
        'is_banned' => false,
    ]);
    $target = User::factory()->create([
        'is_private' => false,
        'is_banned' => false,
    ]);

    $this->actingAs($viewer)
        ->from(route('feed.index'))
        ->post(route('users.follow', $target->username))
        ->assertRedirect(route('feed.index'));

    $this->assertDatabaseHas('follows', [
        'follower_id' => $viewer->id,
        'following_id' => $target->id,
        'status' => 'accepted',
    ]);

    $this->actingAs($viewer)
        ->from(route('feed.index'))
        ->post(route('users.follow', $target->username))
        ->assertRedirect(route('feed.index'));

    $this->assertDatabaseMissing('follows', [
        'follower_id' => $viewer->id,
        'following_id' => $target->id,
    ]);
});
