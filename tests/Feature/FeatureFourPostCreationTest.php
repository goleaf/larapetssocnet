<?php

use App\Actions\Posts\CreatePostAction;
use App\Actions\Posts\PublishPostAction;
use App\Enums\PostStatus;
use App\Jobs\FeedFanOutJob;
use App\Jobs\MediaProcessingJob;
use App\Jobs\MentionNotificationJob;
use App\Jobs\PublishScheduledPostJob;
use App\Models\Content\Hashtag;
use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Models\Pets\Pet;
use App\Services\CanonicalContentUrlService;
use App\Services\PostMentionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('creates a rich post with pet tags hashtags mentions mood location and link preview', function (): void {
    Queue::fake([
        FeedFanOutJob::class,
        MentionNotificationJob::class,
        MediaProcessingJob::class,
    ]);

    $author = User::factory()->create();
    $mentioned = User::factory()->create(['username' => 'luna_friend']);
    $pet = Pet::factory()->for($author)->create(['name' => 'Luna']);

    $result = app(CreatePostAction::class)->handle($author, [
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
    $post = $result->createdPost();

    $fresh = $post->fresh();

    expect($result->duplicateDetected)->toBeFalse()
        ->and($fresh->uuid)->not->toBeNull()
        ->and(Str::isUuid($fresh->uuid))->toBeTrue()
        ->and($fresh->author_type)->toBe(User::class)
        ->and($fresh->author_id)->toBe($author->getKey())
        ->and($fresh->body)->toBe('A park update & picnic for @luna_friend #ParkDay https://example.com/luna')
        ->and($fresh->content_hash)->toBe(hash('sha256', 'a park update & picnic for @luna_friend #parkday https://example.com/luna'))
        ->and($fresh->mood)->toBe('playful')
        ->and($fresh->location_display_text)->toBe('Vilnius Park')
        ->and($fresh->link_preview)->toMatchArray(['url' => 'https://example.com/luna'])
        ->and($fresh->contentAuthor?->is($author))->toBeTrue()
        ->and($post->pets()->whereKey($pet->id)->exists())->toBeTrue()
        ->and($pet->fresh()->posts_count)->toBe(1);

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

    Queue::assertPushed(MentionNotificationJob::class, fn (MentionNotificationJob $job): bool => $job->postId === $post->id
        && $job->mentionedUserId === $mentioned->id
        && $job->authorId === $author->id);
    Queue::assertPushed(FeedFanOutJob::class, fn (FeedFanOutJob $job): bool => $job->postId === $post->id);
});

it('validates post creation input before writing records', function (): void {
    $author = User::factory()->create();

    expect(fn () => app(CreatePostAction::class)->handle($author, [
        'body' => 'Broken preview',
        'visibility' => Post::VISIBILITY_PUBLIC,
        'link_preview' => ['url' => 'not a url'],
    ]))->toThrow(ValidationException::class);

    expect(Post::query()->count())->toBe(0);
});

it('queues temporary media processing jobs after creating the post placeholder state', function (): void {
    Queue::fake([
        FeedFanOutJob::class,
        MentionNotificationJob::class,
        MediaProcessingJob::class,
    ]);

    $author = User::factory()->create();

    $post = app(CreatePostAction::class)->handle($author, [
        'body' => 'Photo from the composer',
        'visibility' => Post::VISIBILITY_PUBLIC,
        'media_attachments' => [
            [
                'temporary_path' => 'livewire-tmp/photo.webp',
                'media_type' => 'image',
                'alt_text' => 'A dog waiting at the park gate',
            ],
        ],
    ])->createdPost();

    expect($post->type)->toBe(Post::TYPE_PHOTO);

    Queue::assertPushed(MediaProcessingJob::class, fn (MediaProcessingJob $job): bool => $job->postId === $post->id
        && $job->temporaryPath === 'livewire-tmp/photo.webp'
        && $job->mediaType === 'image'
        && $job->altText === 'A dog waiting at the park gate');
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

    $duplicate = app(CreatePostAction::class)->handle($author, [
        'body' => '  SAME   exact update  ',
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);

    expect($duplicate->duplicateDetected)->toBeTrue()
        ->and($duplicate->duplicatePostId)->toBe(Post::query()->firstOrFail()->id);

    $confirmed = app(CreatePostAction::class)->handle($author, [
        'body' => 'Same exact update',
        'visibility' => Post::VISIBILITY_PUBLIC,
        'confirmed_duplicate' => true,
    ]);

    expect($confirmed->duplicateDetected)->toBeFalse()
        ->and(Post::query()->count())->toBe(2);
});

it('dispatches due scheduled post publication jobs through the artisan command', function (): void {
    Queue::fake([PublishScheduledPostJob::class]);

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

    Queue::assertPushed(PublishScheduledPostJob::class, fn (PublishScheduledPostJob $job): bool => $job->postId === $due->id);
    Queue::assertNotPushed(PublishScheduledPostJob::class, fn (PublishScheduledPostJob $job): bool => $job->postId === $future->id);

    expect($due->refresh()->status)->toBe(PostStatus::Scheduled)
        ->and($future->refresh()->status)->toBe(PostStatus::Scheduled);
});

it('skips scheduled post dispatch when the command lock is already held', function (): void {
    Queue::fake([PublishScheduledPostJob::class]);

    $author = User::factory()->create();
    Post::factory()->for($author)->create([
        'status' => PostStatus::Scheduled->value,
        'scheduled_publish_at' => now()->subMinute(),
    ]);

    $lock = Cache::store('database')->lock('posts:publish-scheduled-command', 70);
    $lock->get();

    try {
        $this->artisan('posts:publish-scheduled')->assertSuccessful();

        Queue::assertNotPushed(PublishScheduledPostJob::class);
    } finally {
        $lock->release();
    }
});

it('publishes a scheduled post job and dispatches fanout and mention notifications once due', function (): void {
    Queue::fake([
        FeedFanOutJob::class,
        MentionNotificationJob::class,
    ]);

    $author = User::factory()->create();
    $mentioned = User::factory()->create(['username' => 'future_friend']);
    $futureSchedule = now('UTC')->addDay();

    $post = app(CreatePostAction::class)->handle($author, [
        'body' => 'A future update for @future_friend #Soon',
        'visibility' => Post::VISIBILITY_PUBLIC,
        'status' => PostStatus::Scheduled->value,
        'scheduled_publish_at' => $futureSchedule->toIso8601String(),
    ])->createdPost();

    $hashtag = Hashtag::query()->where('normalized_name', 'soon')->firstOrFail();

    expect($post->fresh()->status)->toBe(PostStatus::Scheduled)
        ->and($hashtag->fresh()->posts_count)->toBe(0);

    Queue::assertNotPushed(FeedFanOutJob::class);
    Queue::assertNotPushed(MentionNotificationJob::class);

    $post->forceFill(['scheduled_publish_at' => now()->subMinute()])->saveQuietly();

    (new PublishScheduledPostJob($post->id))->handle(
        app(PublishPostAction::class),
        app(PostMentionService::class),
    );

    expect($post->refresh()->status)->toBe(PostStatus::Published)
        ->and($post->scheduled_publish_at)->toBeNull()
        ->and($hashtag->fresh()->posts_count)->toBe(1);

    Queue::assertPushed(FeedFanOutJob::class, fn (FeedFanOutJob $job): bool => $job->postId === $post->id);
    Queue::assertPushed(MentionNotificationJob::class, fn (MentionNotificationJob $job): bool => $job->postId === $post->id
        && $job->mentionedUserId === $mentioned->id);
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
    ])->createdPost();

    $quote = app(CreatePostAction::class)->handle($author, [
        'body' => 'Adding my take',
        'visibility' => Post::VISIBILITY_PUBLIC,
        'quote_post_id' => $original->id,
    ])->createdPost();

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
