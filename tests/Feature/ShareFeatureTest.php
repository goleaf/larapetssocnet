<?php

use App\Jobs\FeedFanOutJob;
use App\Models\Content\Post;
use App\Models\Content\Share;
use App\Models\Identity\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('tracks a share and increments shares_count', function (): void {
    $user = User::factory()->create();
    $post = Post::factory()->create([
        'user_id' => User::factory()->create()->id,
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);

    $this->actingAs($user)
        ->postJson(route('posts.share', $post), ['method' => 'copy_link'])
        ->assertOk()
        ->assertJsonPath('shares_count', 1);

    $this->assertDatabaseHas('shares', [
        'user_id' => $user->id,
        'shareable_type' => (new Post)->getMorphClass(),
        'shareable_id' => $post->id,
    ]);

    expect($post->refresh()->shares_count)->toBe(1);
});

it('does not double count shares by the same user', function (): void {
    $user = User::factory()->create();
    $post = Post::factory()->create([
        'user_id' => User::factory()->create()->id,
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);

    $this->actingAs($user)
        ->postJson(route('posts.share', $post), ['method' => 'copy_link'])
        ->assertOk();

    $this->actingAs($user)
        ->postJson(route('posts.share', $post), ['method' => 'copy_link'])
        ->assertOk()
        ->assertJsonPath('shares_count', 1);

    expect(Share::query()->count())->toBe(1);
    expect($post->refresh()->shares_count)->toBe(1);
});

it('prevents sharing posts the viewer cannot access', function (): void {
    $author = User::factory()->create(['is_private' => true]);
    $viewer = User::factory()->create();

    $post = Post::factory()->for($author)->create([
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);

    $this->actingAs($viewer)
        ->postJson(route('posts.share', $post), ['method' => 'copy_link'])
        ->assertForbidden();
});

it('creates a repost and increments the original share count', function (): void {
    Queue::fake([FeedFanOutJob::class]);

    $author = User::factory()->create();
    $reposter = User::factory()->create([
        'profile_visibility' => 'followers_only',
    ]);
    $original = Post::factory()->for($author)->create([
        'body' => 'original post worth reposting',
        'visibility' => Post::VISIBILITY_PUBLIC,
        'shares_count' => 0,
    ]);

    $this->actingAs($reposter)
        ->postJson(route('posts.share', $original), ['method' => 'repost'])
        ->assertOk()
        ->assertJsonPath('shares_count', 1)
        ->assertJsonStructure(['repost_id', 'repost_url']);

    $repost = Post::query()
        ->where('user_id', $reposter->id)
        ->where('original_post_id', $original->id)
        ->firstOrFail();

    expect($repost->body)->toBeNull()
        ->and($repost->visibility)->toBe(Post::VISIBILITY_FOLLOWERS)
        ->and($original->fresh()->shares_count)->toBe(1);

    Queue::assertPushed(FeedFanOutJob::class, fn (FeedFanOutJob $job): bool => $job->postId === $repost->id);
});

it('counts reposts even when the user previously copied the link', function (): void {
    Queue::fake([FeedFanOutJob::class]);

    $author = User::factory()->create();
    $reposter = User::factory()->create();
    $original = Post::factory()->for($author)->create([
        'visibility' => Post::VISIBILITY_PUBLIC,
        'shares_count' => 0,
    ]);

    $this->actingAs($reposter)
        ->postJson(route('posts.share', $original), ['method' => 'copy_link'])
        ->assertOk()
        ->assertJsonPath('shares_count', 1);

    $this->actingAs($reposter)
        ->postJson(route('posts.share', $original), ['method' => 'repost'])
        ->assertOk()
        ->assertJsonPath('shares_count', 2);

    expect($original->fresh()->shares_count)->toBe(2);
});

it('renders the share menu options from the post card share trigger', function (): void {
    $viewer = User::factory()->create();
    $post = Post::factory()->create([
        'user_id' => User::factory()->create()->id,
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);

    Livewire::actingAs($viewer)
        ->test('posts.share-menu', ['post' => $post, 'authorName' => 'Mina'])
        ->assertSee('Share')
        ->assertSee('Repost')
        ->assertSee('Quote post')
        ->assertSee('Copy link')
        ->assertSee('Link copied!');
});
