<?php

use App\Actions\Posts\CreatePostAction;
use App\Jobs\FeedFanOutChunkJob;
use App\Jobs\FeedFanOutJob;
use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Models\Social\FeedItem;
use App\Models\Social\Follow;
use App\Services\PostDraftService;
use App\Support\Posts\PostCreationInput;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('exposes a dto only public post creation contract', function (): void {
    $method = new ReflectionMethod(CreatePostAction::class, 'handle');
    $inputParameter = $method->getParameters()[1];
    $type = $inputParameter->getType();

    expect($type)->toBeInstanceOf(ReflectionNamedType::class)
        ->and($type->getName())->toBe(PostCreationInput::class);
});

it('skips identical autosave writes by comparing the serialized draft state hash', function (): void {
    $user = User::factory()->create();
    $service = app(PostDraftService::class);
    $payload = [
        'text_content' => 'A stable draft body',
        'selected_visibility' => Post::VISIBILITY_FOLLOWERS,
        'selected_pet_ids' => [12, 13],
    ];

    $first = $service->autosave($user, $payload, 'composer', 7);
    $firstUpdatedAt = $first->updated_at?->toISOString();

    $second = $service->autosave($user, $payload, 'composer', 7);

    expect($first->state_hash)->toBeString()
        ->and($second->id)->toBe($first->id)
        ->and($second->state_hash)->toBe($first->state_hash)
        ->and($second->updated_at?->toISOString())->toBe($firstUpdatedAt);
});

it('marks feed fanout complete and treats duplicate fanout jobs as no ops', function (): void {
    Queue::fake([FeedFanOutChunkJob::class]);

    $author = User::factory()->create();
    $follower = User::factory()->create();
    Follow::factory()->create([
        'follower_id' => $follower->getKey(),
        'following_id' => $author->getKey(),
        'status' => 'accepted',
    ]);
    $post = Post::factory()->for($author)->create([
        'is_fanned_out' => false,
    ]);

    (new FeedFanOutJob((int) $post->getKey()))->handle();

    expect($post->refresh()->is_fanned_out)->toBeTrue()
        ->and(Cache::get('posts:fanout:last:'.$post->getKey())['recipient_count'] ?? null)->toBe(1)
        ->and(Cache::get('posts:fanout:last:'.$post->getKey())['feed_item_count'] ?? null)->toBe(2);

    Queue::assertPushed(FeedFanOutChunkJob::class, 2);
    Queue::assertPushed(
        FeedFanOutChunkJob::class,
        fn (FeedFanOutChunkJob $job): bool => $job->postId === $post->id
            && $job->items[0]['user_id'] === $follower->id
            && $job->items[0]['source_type'] === FeedItem::SOURCE_USER
    );

    Cache::put('posts:fanout:last:'.$post->getKey(), [
        'post_id' => (int) $post->getKey(),
        'recipient_count' => 999,
    ], now()->addDay());

    (new FeedFanOutJob((int) $post->getKey()))->handle();

    expect(Cache::get('posts:fanout:last:'.$post->getKey())['recipient_count'] ?? null)->toBe(999);
    Queue::assertPushed(FeedFanOutChunkJob::class, 2);
});

it('writes precomputed feed items idempotently from fanout chunks', function (): void {
    $author = User::factory()->create();
    $viewer = User::factory()->create();
    $post = Post::factory()->for($author)->create([
        'status' => 'published',
    ]);

    $job = new FeedFanOutChunkJob((int) $post->getKey(), [[
        'user_id' => (int) $viewer->getKey(),
        'source_type' => FeedItem::SOURCE_USER,
        'source_id' => (int) $author->getKey(),
    ]], $post->created_at?->toDateTimeString());

    $job->handle();
    $job->handle();

    expect(FeedItem::query()->where('post_id', $post->getKey())->count())->toBe(1);

    $this->assertDatabaseHas('feed_items', [
        'user_id' => $viewer->getKey(),
        'post_id' => $post->getKey(),
        'source_type' => FeedItem::SOURCE_USER,
        'source_id' => $author->getKey(),
    ]);
});
