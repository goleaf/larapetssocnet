<?php

use App\Models\Content\Post;
use App\Models\Identity\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('saves and unsaves a post by toggling saved state', function (): void {
    $user = User::factory()->create();
    $post = Post::factory()->create([
        'user_id' => User::factory()->create()->id,
        'visibility' => 'public',
    ]);

    $this->actingAs($user)
        ->postJson(route('posts.save', $post))
        ->assertOk()
        ->assertJsonPath('saved', true);

    $this->assertDatabaseHas('saved_posts', [
        'user_id' => $user->id,
        'post_id' => $post->id,
    ]);
    expect((int) $post->fresh()->save_count)->toBe(1);

    $this->actingAs($user)
        ->postJson(route('posts.save', $post))
        ->assertOk()
        ->assertJsonPath('saved', false);

    $this->assertDatabaseMissing('saved_posts', [
        'user_id' => $user->id,
        'post_id' => $post->id,
    ]);
    expect((int) $post->fresh()->save_count)->toBe(0);
});

it('lists only saved posts visible to the viewer on saved index', function (): void {
    $viewer = User::factory()->create([
        'is_private' => false,
        'is_banned' => false,
    ]);

    $publicAuthor = User::factory()->create([
        'is_private' => false,
        'is_banned' => false,
    ]);

    $privateAuthor = User::factory()->create([
        'is_private' => true,
        'is_banned' => false,
    ]);

    $visiblePost = Post::factory()->for($publicAuthor)->create([
        'body' => 'saved-visible-post',
        'body_html' => '<p>saved-visible-post</p>',
        'visibility' => Post::VISIBILITY_PUBLIC,
        'status' => 'published',
    ]);

    $hiddenPost = Post::factory()->for($privateAuthor)->create([
        'body' => 'saved-hidden-post',
        'body_html' => '<p>saved-hidden-post</p>',
        'visibility' => Post::VISIBILITY_PUBLIC,
        'status' => 'published',
    ]);

    $viewer->savedPosts()->attach([$visiblePost->id, $hiddenPost->id]);

    $this->actingAs($viewer)
        ->get(route('saved.index'))
        ->assertOk()
        ->assertSee('saved-visible-post')
        ->assertDontSee('saved-hidden-post');
});
