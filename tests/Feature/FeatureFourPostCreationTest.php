<?php

use App\Actions\Posts\CreatePostAction;
use App\Enums\PostStatus;
use App\Models\Content\Hashtag;
use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Models\Pets\Pet;
use App\Notifications\MentionedInPost;
use App\Services\CanonicalContentUrlService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('creates a rich post with pet tags hashtags mentions mood location and link preview', function (): void {
    Notification::fake();

    $author = User::factory()->create();
    $mentioned = User::factory()->create(['username' => 'luna_friend']);
    $pet = Pet::factory()->for($author)->create(['name' => 'Luna']);

    $post = app(CreatePostAction::class)->handle($author, [
        'body' => '<strong>A park update</strong> &amp; picnic for @luna_friend #ParkDay https://example.com/luna',
        'visibility' => Post::VISIBILITY_PUBLIC,
        'mood' => 'playful',
        'location' => 'Vilnius Park',
        'location_lat' => 54.6872,
        'location_lng' => 25.2797,
        'tagged_pets' => [$pet->id],
        'metadata' => [
            'link' => [
                'url' => 'https://example.com/luna',
                'title' => 'Luna at the park',
            ],
        ],
    ]);

    $fresh = $post->fresh();

    expect($fresh->uuid)->not->toBeNull()
        ->and(Str::isUuid($fresh->uuid))->toBeTrue()
        ->and($fresh->author_type)->toBe(User::class)
        ->and($fresh->author_id)->toBe($author->getKey())
        ->and($fresh->body)->toBe('A park update & picnic for @luna_friend #ParkDay https://example.com/luna')
        ->and($fresh->mood)->toBe('playful')
        ->and($fresh->location_display_text)->toBe('Vilnius Park')
        ->and($fresh->link_preview)->toMatchArray(['url' => 'https://example.com/luna'])
        ->and($fresh->contentAuthor?->is($author))->toBeTrue()
        ->and($post->pets()->whereKey($pet->id)->exists())->toBeTrue();

    $hashtag = Hashtag::query()->where('normalized_name', 'parkday')->firstOrFail();

    $this->assertDatabaseHas('post_hashtag', [
        'post_id' => $post->id,
        'hashtag_id' => $hashtag->id,
    ]);
    $this->assertDatabaseHas('post_mentions', [
        'post_id' => $post->id,
        'mentioned_user_id' => $mentioned->id,
        'mentioned_username' => 'luna_friend',
    ]);

    Notification::assertSentTo($mentioned, MentionedInPost::class);
});

it('uses uuid post URLs for sharing while still resolving legacy integer post routes', function (): void {
    $author = User::factory()->create();
    $post = Post::factory()->for($author)->create();

    $shareUrl = app(CanonicalContentUrlService::class)->post($post);

    expect($shareUrl)->toContain($post->uuid)
        ->and($shareUrl)->not->toContain('/posts/'.$post->id);

    $this->actingAs($author)->get($shareUrl)->assertOk();
    $this->actingAs($author)->get(route('posts.show', ['post' => $post->id]))->assertOk();
});

it('prevents duplicate non-draft submissions with identical text inside twenty four hours', function (): void {
    $author = User::factory()->create();

    app(CreatePostAction::class)->handle($author, [
        'body' => 'Same exact update',
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);

    expect(fn () => app(CreatePostAction::class)->handle($author, [
        'body' => 'Same exact update',
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]))->toThrow(ValidationException::class);
});

it('publishes due scheduled posts through the artisan command and leaves future posts scheduled', function (): void {
    $author = User::factory()->create();
    $due = Post::factory()->for($author)->create([
        'status' => PostStatus::Scheduled->value,
        'published_at' => now()->subMinute(),
        'scheduled_publish_at' => now()->subMinute(),
    ]);
    $future = Post::factory()->for($author)->create([
        'status' => PostStatus::Scheduled->value,
        'published_at' => now()->addHour(),
        'scheduled_publish_at' => now()->addHour(),
    ]);

    $this->artisan('posts:publish-scheduled')->assertSuccessful();

    expect($due->refresh()->status)->toBe(PostStatus::Published)
        ->and($due->scheduled_publish_at)->toBeNull()
        ->and($future->refresh()->status)->toBe(PostStatus::Scheduled);
});

it('blocks editing published posts after the twenty four hour edit window', function (): void {
    $author = User::factory()->create();
    $post = Post::factory()->for($author)->create([
        'created_at' => now()->subHours(25),
        'status' => PostStatus::Published->value,
    ]);

    $this->actingAs($author)
        ->patch(route('posts.update', $post), [
            'body' => 'Too late to edit',
            'visibility' => Post::VISIBILITY_PUBLIC,
        ])
        ->assertForbidden();
});

it('stores repost and quote references as new post records', function (): void {
    $author = User::factory()->create();
    $original = Post::factory()->for($author)->create();

    $repost = app(CreatePostAction::class)->handle($author, [
        'body' => null,
        'visibility' => Post::VISIBILITY_PUBLIC,
        'original_post_id' => $original->id,
    ]);

    $quote = app(CreatePostAction::class)->handle($author, [
        'body' => 'Adding my take',
        'visibility' => Post::VISIBILITY_PUBLIC,
        'quote_post_id' => $original->id,
    ]);

    expect($repost->original_post_id)->toBe($original->id)
        ->and($quote->quote_post_id)->toBe($original->id)
        ->and($repost->id)->not->toBe($original->id)
        ->and($quote->id)->not->toBe($original->id);
});

it('excludes private posts from another users feed query', function (): void {
    $author = User::factory()->create();
    $viewer = User::factory()->create();
    $private = Post::factory()->for($author)->create([
        'visibility' => Post::VISIBILITY_PRIVATE,
        'status' => PostStatus::Published->value,
        'published_at' => now(),
    ]);

    $postIds = Post::query()
        ->visibleTo($viewer)
        ->pluck('posts.id')
        ->all();

    expect($postIds)->not->toContain($private->id);
});
