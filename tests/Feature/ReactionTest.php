<?php

use App\Models\Content\Post;
use App\Models\Content\Reaction;
use App\Models\Identity\User;
use App\Models\Pets\Pet;
use App\Notifications\NewReaction;
use App\Services\SyncReactionCountsService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('toggles post reactions and updates likes_count', function (): void {
    $user = User::factory()->create();
    $post = Post::factory()->create([
        'user_id' => User::factory()->create()->id,
        'visibility' => 'public',
    ]);

    $this->actingAs($user)
        ->postJson(route('posts.react', $post), ['type' => 'love'])
        ->assertOk()
        ->assertJsonPath('data.likes_count', 1)
        ->assertJsonPath('data.reactions_count', 1)
        ->assertJsonPath('data.reaction_counts.love', 1)
        ->assertJsonPath('data.current_reaction', 'love');

    $this->assertDatabaseHas('reactions', [
        'reactable_type' => (new Post)->getMorphClass(),
        'reactable_id' => $post->id,
        'user_id' => $user->id,
        'type' => 'love',
    ]);

    $this->assertDatabaseHas('posts', [
        'id' => $post->id,
        'likes_count' => 1,
        'reactions_count' => 1,
        'love_count' => 1,
        'cute_count' => 0,
    ]);

    $this->actingAs($user)
        ->postJson(route('posts.react', $post), ['type' => 'cute'])
        ->assertOk()
        ->assertJsonPath('data.likes_count', 1)
        ->assertJsonPath('data.reactions_count', 1)
        ->assertJsonPath('data.reaction_counts.love', 0)
        ->assertJsonPath('data.reaction_counts.cute', 1)
        ->assertJsonPath('data.current_reaction', 'cute');

    $this->assertDatabaseHas('posts', [
        'id' => $post->id,
        'likes_count' => 1,
        'reactions_count' => 1,
        'love_count' => 0,
        'cute_count' => 1,
    ]);

    $this->actingAs($user)
        ->postJson(route('posts.react', $post), ['type' => 'cute'])
        ->assertOk()
        ->assertJsonPath('data.likes_count', 0)
        ->assertJsonPath('data.reactions_count', 0)
        ->assertJsonPath('data.reaction_counts.cute', 0)
        ->assertJsonPath('data.current_reaction', null);

    $this->assertDatabaseHas('posts', [
        'id' => $post->id,
        'likes_count' => 0,
        'reactions_count' => 0,
        'love_count' => 0,
        'cute_count' => 0,
    ]);
});

it('blocks guests from reacting to posts', function (): void {
    $post = Post::factory()->create([
        'user_id' => User::factory()->create()->id,
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);

    $this->postJson(route('posts.react', $post), ['type' => 'love'])
        ->assertUnauthorized();
});

it('reacts to posts tagged to pet profiles', function (): void {
    $actor = User::factory()->create();
    $author = User::factory()->create();
    $pet = Pet::factory()->create([
        'user_id' => $author->getKey(),
    ]);
    $post = Post::factory()->create([
        'user_id' => $author->getKey(),
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);

    $post->pets()->attach($pet->getKey(), ['is_primary' => true]);

    $this->actingAs($actor)
        ->postJson(route('posts.react', $post), ['type' => 'support'])
        ->assertOk()
        ->assertJsonPath('data.likes_count', 1)
        ->assertJsonPath('data.reaction_counts.support', 1)
        ->assertJsonPath('data.current_reaction', 'support');

    $this->assertDatabaseHas('reactions', [
        'reactable_type' => (new Post)->getMorphClass(),
        'reactable_id' => $post->getKey(),
        'user_id' => $actor->getKey(),
        'type' => 'support',
    ]);
});

it('enforces one active reaction per user and post at the database layer', function (): void {
    $user = User::factory()->create();
    $post = Post::factory()->create([
        'user_id' => User::factory()->create()->id,
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);

    Reaction::query()->create([
        'reactable_type' => (new Post)->getMorphClass(),
        'reactable_id' => $post->getKey(),
        'user_id' => $user->getKey(),
        'type' => Reaction::TYPE_LOVE,
    ]);

    expect(fn () => Reaction::query()->create([
        'reactable_type' => (new Post)->getMorphClass(),
        'reactable_id' => $post->getKey(),
        'user_id' => $user->getKey(),
        'type' => Reaction::TYPE_CUTE,
    ]))->toThrow(QueryException::class);

    expect(Reaction::query()
        ->where('reactable_type', (new Post)->getMorphClass())
        ->where('reactable_id', $post->getKey())
        ->where('user_id', $user->getKey())
        ->count())->toBe(1);
});

it('keeps counter caches accurate after bulk reaction sync', function (): void {
    $post = Post::factory()->create([
        'user_id' => User::factory()->create()->id,
        'visibility' => Post::VISIBILITY_PUBLIC,
        'likes_count' => 42,
        'reactions_count' => 42,
        'love_count' => 42,
        'cute_count' => 42,
        'support_count' => 42,
    ]);

    foreach ([Reaction::TYPE_LOVE, Reaction::TYPE_CUTE, Reaction::TYPE_CUTE, Reaction::TYPE_SUPPORT] as $type) {
        Reaction::query()->create([
            'reactable_type' => (new Post)->getMorphClass(),
            'reactable_id' => $post->getKey(),
            'user_id' => User::factory()->create()->getKey(),
            'type' => $type,
        ]);
    }

    app(SyncReactionCountsService::class)->sync($post);

    $post->refresh();

    expect($post->likes_count)->toBe(4)
        ->and($post->reactions_count)->toBe(4)
        ->and($post->love_count)->toBe(1)
        ->and($post->cute_count)->toBe(2)
        ->and($post->funny_count)->toBe(0)
        ->and($post->support_count)->toBe(1);
});

it('accepts all supported reaction types and rejects invalid type', function (): void {
    $user = User::factory()->create();
    $post = Post::factory()->create([
        'user_id' => User::factory()->create()->id,
        'visibility' => 'public',
    ]);

    foreach (['love', 'cute', 'funny', 'wow', 'sad', 'support'] as $type) {
        $this->actingAs($user)
            ->postJson(route('posts.react', $post), ['type' => $type])
            ->assertOk()
            ->assertJsonPath('data.current_reaction', $type);
    }

    $this->actingAs($user)
        ->postJson(route('posts.react', $post), ['type' => 'angry'])
        ->assertInvalid(['type']);
});

it('sends reaction notification with relation-light models', function (): void {
    Notification::fake();

    $actor = User::factory()->create();
    $author = User::factory()->create();
    $post = Post::factory()->create([
        'user_id' => $author->id,
        'visibility' => 'public',
    ]);

    Post::factory()->create([
        'user_id' => $actor->id,
        'visibility' => 'public',
    ]);

    $post->load('author');
    $actor->load('media', 'posts');

    $this->actingAs($actor)
        ->postJson(route('posts.react', $post), ['type' => 'love'])
        ->assertOk();

    expect($actor->relationLoaded('media'))->toBeTrue();
    expect($actor->relationLoaded('posts'))->toBeTrue();
    expect($post->relationLoaded('author'))->toBeTrue();

    Notification::assertSentTo($author, NewReaction::class, function (NewReaction $notification): bool {
        return ! $notification->post->relationLoaded('author')
            && ! $notification->reactor->relationLoaded('media')
            && $notification->reactor->relationLoaded('posts');
    });
});

it('prevents blocked users from reacting to posts', function (): void {
    $author = User::factory()->create();
    $viewer = User::factory()->create();

    $post = Post::factory()->for($author)->create([
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);

    $viewer->blocking()->attach($author->id);

    $this->actingAs($viewer)
        ->postJson(route('posts.react', $post), ['type' => 'love'])
        ->assertForbidden();
});
