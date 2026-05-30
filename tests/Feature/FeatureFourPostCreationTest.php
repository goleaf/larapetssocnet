<?php

use App\Actions\Posts\CreatePostAction;
use App\Actions\Posts\UpdatePostAction;
use App\Enums\PostStatus;
use App\Models\Content\Hashtag;
use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Models\Pets\Pet;
use App\Models\Social\FeedItem;
use App\Notifications\Database\Posts\MentionedInPost;
use App\Services\CanonicalContentUrlService;
use App\Services\PostLinkPreviewService;
use App\Services\ScheduledPostPublisherService;
use App\Support\Posts\PostCreationInput;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('creates a rich post with pet tags hashtags mentions mood location and link preview', function (): void {
    Notification::fake();

    $author = User::factory()->create();
    $mentioned = User::factory()->create(['username' => 'luna_friend']);
    $pet = Pet::factory()->for($author)->create(['name' => 'Luna']);

    $result = app(CreatePostAction::class)->handle($author, PostCreationInput::fromUserInput($author, [
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
    ]));
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

    Notification::assertSentTo($mentioned, MentionedInPost::class);
    $this->assertDatabaseHas('feed_items', [
        'user_id' => $author->id,
        'post_id' => $post->id,
        'source_type' => FeedItem::SOURCE_SELF,
        'source_id' => $author->id,
    ]);
});

it('validates post creation input before writing records', function (): void {
    $author = User::factory()->create();

    expect(fn () => app(CreatePostAction::class)->handle($author, PostCreationInput::fromUserInput($author, [
        'body' => 'Broken preview',
        'visibility' => Post::VISIBILITY_PUBLIC,
        'link_preview' => ['url' => 'not a url'],
    ])))->toThrow(ValidationException::class);

    expect(Post::query()->count())->toBe(0);
});

it('processes temporary media after creating the post placeholder state', function (): void {
    $author = User::factory()->create();

    $post = app(CreatePostAction::class)->handle($author, PostCreationInput::fromUserInput($author, [
        'body' => 'Photo from the composer',
        'visibility' => Post::VISIBILITY_PUBLIC,
        'media_attachments' => [
            [
                'temporary_path' => 'livewire-tmp/photo.webp',
                'media_type' => 'image',
                'alt_text' => 'A dog waiting at the park gate',
            ],
        ],
    ]))->createdPost();

    expect($post->type)->toBe(Post::TYPE_PHOTO);
    $this->assertDatabaseHas('post_media', [
        'post_id' => $post->id,
        'file_path' => 'livewire-tmp/photo.webp',
        'media_type' => 'image',
        'alt_text' => 'A dog waiting at the park gate',
        'processing_status' => 'failed',
        'order' => 0,
    ]);
});

it('rejects temporary media paths outside the Livewire upload directory', function (): void {
    $author = User::factory()->create();

    expect(fn () => app(CreatePostAction::class)->handle($author, PostCreationInput::fromUserInput($author, [
        'body' => 'Photo from a forged composer payload',
        'visibility' => Post::VISIBILITY_PUBLIC,
        'media_attachments' => [
            [
                'temporary_path' => '/etc/passwd',
                'media_type' => 'image',
                'alt_text' => 'A forged upload path',
            ],
        ],
    ])))->toThrow(ValidationException::class);

    expect(Post::query()->count())->toBe(0);
});

it('creates posts immediately and fetches link preview metadata when it is not preloaded', function (): void {
    $author = User::factory()->create();
    $this->instance(PostLinkPreviewService::class, new class extends PostLinkPreviewService
    {
        public function __construct() {}

        public function fetch(string $url, ?int $postId = null, ?string $cacheKey = null): void
        {
            if ($postId === null) {
                return;
            }

            Post::query()
                ->whereKey($postId)
                ->update([
                    'link_preview' => [
                        'url' => $url,
                        'title' => 'Adoption update',
                        'domain' => 'example.com',
                    ],
                ]);
        }
    });

    $post = app(CreatePostAction::class)->handle($author, PostCreationInput::fromUserInput($author, [
        'body' => 'Read this adoption update https://example.com/adoption',
        'visibility' => Post::VISIBILITY_PUBLIC,
        'link_preview_url' => 'https://example.com/adoption',
    ]))->createdPost();

    expect($post->fresh()->link_preview)->toMatchArray([
        'url' => 'https://example.com/adoption',
        'title' => 'Adoption update',
    ]);
    $this->assertDatabaseHas('feed_items', [
        'user_id' => $author->id,
        'post_id' => $post->id,
        'source_type' => FeedItem::SOURCE_SELF,
    ]);
});

it('uses uuid post URLs for sharing while still resolving legacy integer post routes', function (): void {
    $author = User::factory()->create();
    $post = Post::factory()->for($author)->create();

    $shareUrl = app(CanonicalContentUrlService::class)->post($post);

    expect($shareUrl)->toContain('/posts/'.$post->uuid)
        ->and($shareUrl)->not->toEndWith('/posts/'.$post->id);

    $this->actingAs($author)->get($shareUrl)->assertOk();
    $this->actingAs($author)->get(route('posts.show', ['post' => $post->id]))->assertOk();
});

it('prevents duplicate non-draft submissions with identical text inside twenty four hours', function (): void {
    $author = User::factory()->create();

    app(CreatePostAction::class)->handle($author, PostCreationInput::fromUserInput($author, [
        'body' => 'Same exact update',
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]));

    $duplicate = app(CreatePostAction::class)->handle($author, PostCreationInput::fromUserInput($author, [
        'body' => '  SAME   exact update  ',
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]));

    expect($duplicate->duplicateDetected)->toBeTrue()
        ->and($duplicate->duplicatePostId)->toBe(Post::query()->firstOrFail()->id);

    $confirmed = app(CreatePostAction::class)->handle($author, PostCreationInput::fromUserInput($author, [
        'body' => 'Same exact update',
        'visibility' => Post::VISIBILITY_PUBLIC,
        'confirmed_duplicate' => true,
    ]));

    expect($confirmed->duplicateDetected)->toBeFalse()
        ->and(Post::query()->count())->toBe(2);
});

it('publishes due scheduled posts through the artisan command', function (): void {
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
        ->and($future->refresh()->status)->toBe(PostStatus::Scheduled);
});

it('skips scheduled post publication when the command lock is already held', function (): void {
    $author = User::factory()->create();
    $post = Post::factory()->for($author)->create([
        'status' => PostStatus::Scheduled->value,
        'scheduled_publish_at' => now()->subMinute(),
    ]);

    $lock = Cache::store('database')->lock('posts:publish-scheduled-command', 70);
    $lock->get();

    try {
        $this->artisan('posts:publish-scheduled')->assertSuccessful();
        expect($post->refresh()->status)->toBe(PostStatus::Scheduled);
    } finally {
        $lock->release();
    }
});

it('publishes a scheduled post and runs fanout and mention notifications once due', function (): void {
    Notification::fake();
    $author = User::factory()->create();
    $mentioned = User::factory()->create(['username' => 'future_friend']);
    $futureSchedule = now('UTC')->addDay();

    $post = app(CreatePostAction::class)->handle($author, PostCreationInput::fromUserInput($author, [
        'body' => 'A future update for @future_friend #Soon',
        'visibility' => Post::VISIBILITY_PUBLIC,
        'status' => PostStatus::Scheduled->value,
        'scheduled_publish_at' => $futureSchedule->toIso8601String(),
    ]))->createdPost();

    $hashtag = Hashtag::query()->where('normalized_name', 'soon')->firstOrFail();

    expect($post->fresh()->status)->toBe(PostStatus::Scheduled)
        ->and($hashtag->fresh()->posts_count)->toBe(0);

    expect(FeedItem::query()->where('post_id', $post->id)->exists())->toBeFalse();
    Notification::assertNothingSent();

    $post->forceFill(['scheduled_publish_at' => now()->subMinute()])->saveQuietly();

    app(ScheduledPostPublisherService::class)->publish($post->id);

    expect($post->refresh()->status)->toBe(PostStatus::Published)
        ->and($post->scheduled_publish_at)->toBeNull()
        ->and($hashtag->fresh()->posts_count)->toBe(1);

    $this->assertDatabaseHas('feed_items', [
        'post_id' => $post->id,
        'user_id' => $author->id,
        'source_type' => FeedItem::SOURCE_SELF,
    ]);
    Notification::assertSentTo($mentioned, MentionedInPost::class);
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

it('returns a specific action error when editing after the twenty four hour edit window', function (): void {
    $author = User::factory()->create();
    $post = Post::factory()->for($author)->create([
        'body' => 'Original',
        'created_at' => now()->subHours(25),
        'status' => PostStatus::Published->value,
    ]);

    expect(fn () => app(UpdatePostAction::class)->handle($author, $post, [
        'body' => 'Too late to edit',
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]))->toThrow(ValidationException::class, 'Posts can only be edited within 24 hours of creation.');

    expect($post->fresh()->body)->toBe('Original');
});

it('stores repost and quote references as new post records', function (): void {
    $author = User::factory()->create();
    $original = Post::factory()->for($author)->create();

    $repost = app(CreatePostAction::class)->handle($author, PostCreationInput::fromUserInput($author, [
        'body' => null,
        'visibility' => Post::VISIBILITY_PUBLIC,
        'original_post_id' => $original->id,
    ]))->createdPost();

    $quote = app(CreatePostAction::class)->handle($author, PostCreationInput::fromUserInput($author, [
        'body' => 'Adding my take',
        'visibility' => Post::VISIBILITY_PUBLIC,
        'quote_post_id' => $original->id,
    ]))->createdPost();

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
