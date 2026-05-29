<?php

use App\Jobs\DeletePostCascadeJob;
use App\Models\Content\Comment;
use App\Models\Content\Hashtag;
use App\Models\Content\Post;
use App\Models\Content\PostMedia;
use App\Models\Identity\User;
use App\Models\Pets\Pet;
use App\Services\HashtagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Cache::flush();
});

it('opens a deliberate delete confirmation modal with a post preview', function (): void {
    $owner = User::factory()->create();
    $post = Post::factory()->for($owner)->create([
        'body' => str_repeat('Delete preview ', 20),
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);
    PostMedia::factory()->for($post, 'post')->create([
        'file_path' => 'posts/delete-preview.jpg',
        'media_type' => 'image',
        'order' => 0,
    ]);

    Livewire::actingAs($owner)
        ->test('posts.delete-trigger', ['post' => $post])
        ->call('open')
        ->assertSet('open', true)
        ->assertSee('Delete post?')
        ->assertSee("Delete this post? This action cannot be undone. The post will be permanently removed from your profile, your followers' feeds, and all other places it appears.", false)
        ->assertSee('Delete preview')
        ->assertSee('Delete post')
        ->assertSee('Cancel');
});

it('dispatches the queued delete cascade and emits an optimistic removal event', function (): void {
    Queue::fake();

    $owner = User::factory()->create();
    $post = Post::factory()->for($owner)->create();

    Livewire::actingAs($owner)
        ->test('posts.delete-trigger', ['post' => $post])
        ->call('open')
        ->call('confirm')
        ->assertSet('open', false)
        ->assertDispatched('post-delete-requested', postId: $post->id);

    Queue::assertPushed(DeletePostCascadeJob::class, fn (DeletePostCascadeJob $job): bool => $job->postId === $post->id
        && $job->actorId === $owner->id);

    $this->assertDatabaseHas('posts', [
        'id' => $post->id,
        'deleted_at' => null,
    ]);
});

it('runs the queued deletion cascade without silently removing saved placeholders', function (): void {
    $owner = User::factory()->create();
    $saver = User::factory()->create();
    $primaryPet = Pet::factory()->for($owner)->create(['posts_count' => 1]);
    $secondaryPet = Pet::factory()->for($owner)->create(['posts_count' => 1]);
    $hashtag = Hashtag::factory()->create(['posts_count' => 1]);
    $post = Post::factory()->for($owner)->create([
        'body' => '#deletecascade update',
        'pet_id' => $primaryPet->id,
        'tagged_pets' => [$primaryPet->id, $secondaryPet->id],
        'status' => 'published',
        'published_at' => now(),
        'save_count' => 1,
    ]);
    $post->pets()->attach([
        $primaryPet->id => ['is_primary' => true],
        $secondaryPet->id => ['is_primary' => false],
    ]);
    $post->hashtags()->attach($hashtag->id, ['post_created_at' => $post->created_at]);
    $saver->savedPosts()->attach($post->id);
    $comment = Comment::factory()->for($post, 'post')->create();
    $media = PostMedia::factory()->for($post, 'post')->create();

    (new DeletePostCascadeJob($post->id, $owner->id))->handle(app(HashtagService::class));

    $this->assertSoftDeleted('posts', ['id' => $post->id]);
    $this->assertSoftDeleted('comments', ['id' => $comment->id]);
    $this->assertSoftDeleted('post_media', ['id' => $media->id]);
    $this->assertDatabaseMissing('post_hashtag', [
        'post_id' => $post->id,
        'hashtag_id' => $hashtag->id,
    ]);
    $this->assertDatabaseHas('saved_posts', [
        'user_id' => $saver->id,
        'post_id' => $post->id,
    ]);

    expect($primaryPet->fresh()->posts_count)->toBe(0)
        ->and($secondaryPet->fresh()->posts_count)->toBe(0)
        ->and($hashtag->fresh()->posts_count)->toBe(0);
});

it('shows a deleted placeholder for saved posts instead of silently removing them', function (): void {
    $viewer = User::factory()->create();
    $author = User::factory()->create(['is_private' => false]);
    $post = Post::factory()->for($author)->create([
        'body' => 'saved post that was deleted later',
        'body_html' => '<p>saved post that was deleted later</p>',
        'visibility' => Post::VISIBILITY_PUBLIC,
        'status' => 'published',
    ]);

    $viewer->savedPosts()->attach($post->id);
    $post->delete();

    $this->actingAs($viewer)
        ->get(route('saved.index'))
        ->assertOk()
        ->assertSee('This post has been deleted')
        ->assertSee('It remains in your saved posts so you know why this saved item is unavailable.')
        ->assertDontSee('saved post that was deleted later');
});
