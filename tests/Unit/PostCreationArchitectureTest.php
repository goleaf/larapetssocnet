<?php

use App\Actions\Posts\CreatePostAction;
use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Models\Social\FeedItem;
use App\Models\Social\Follow;
use App\Services\FeedFanOutService;
use App\Services\PostDraftService;
use App\Support\Posts\PostCreationInput;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

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

it('marks feed fanout complete and treats duplicate fanout requests as no ops', function (): void {
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

    app(FeedFanOutService::class)->fanOutPost((int) $post->getKey());

    expect($post->refresh()->is_fanned_out)->toBeTrue()
        ->and(Cache::get('posts:fanout:last:'.$post->getKey())['recipient_count'] ?? null)->toBe(1)
        ->and(Cache::get('posts:fanout:last:'.$post->getKey())['feed_item_count'] ?? null)->toBe(2);

    $this->assertDatabaseHas('feed_items', [
        'post_id' => $post->id,
        'user_id' => $follower->id,
        'source_type' => FeedItem::SOURCE_USER,
    ]);

    Cache::put('posts:fanout:last:'.$post->getKey(), [
        'post_id' => (int) $post->getKey(),
        'recipient_count' => 999,
    ], now()->addDay());

    app(FeedFanOutService::class)->fanOutPost((int) $post->getKey());

    expect(Cache::get('posts:fanout:last:'.$post->getKey())['recipient_count'] ?? null)->toBe(999);
});

it('writes precomputed feed items idempotently from fanout service', function (): void {
    $author = User::factory()->create();
    $viewer = User::factory()->create();
    Follow::factory()->create([
        'follower_id' => $viewer->getKey(),
        'following_id' => $author->getKey(),
        'status' => 'accepted',
    ]);
    $post = Post::factory()->for($author)->create([
        'status' => 'published',
        'is_fanned_out' => false,
    ]);

    app(FeedFanOutService::class)->fanOutPost((int) $post->getKey());
    $post->forceFill(['is_fanned_out' => false])->saveQuietly();
    app(FeedFanOutService::class)->fanOutPost((int) $post->getKey());

    expect(FeedItem::query()->where('post_id', $post->getKey())->count())->toBe(2);

    $this->assertDatabaseHas('feed_items', [
        'user_id' => $viewer->getKey(),
        'post_id' => $post->getKey(),
        'source_type' => FeedItem::SOURCE_USER,
        'source_id' => $author->getKey(),
    ]);
});
