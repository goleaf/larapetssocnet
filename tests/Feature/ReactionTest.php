<?php

use App\Models\Content\Post;
use App\Models\Content\Reaction;
use App\Models\Identity\User;
use App\Models\Pets\Pet;
use App\Notifications\NewReaction;
use App\Services\SyncReactionCountsService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('toggles post reactions and updates configured counter caches', function (): void {
    $user = User::factory()->create();
    $post = Post::factory()->create([
        'user_id' => User::factory()->create()->id,
        'visibility' => 'public',
    ]);

    $this->actingAs($user)
        ->postJson(route('posts.react', $post), ['type' => 'paw'])
        ->assertOk()
        ->assertJsonPath('data.likes_count', 1)
        ->assertJsonPath('data.reactions_count', 1)
        ->assertJsonPath('data.reaction_counts.paw', 1)
        ->assertJsonPath('data.current_reaction', 'paw');

    $this->assertDatabaseHas('reactions', [
        'reactable_type' => (new Post)->getMorphClass(),
        'reactable_id' => $post->id,
        'user_id' => $user->id,
        'type' => 'paw',
    ]);

    $this->assertDatabaseHas('posts', [
        'id' => $post->id,
        'likes_count' => 1,
        'reactions_count' => 1,
        'paw_count' => 1,
        'love_count' => 0,
    ]);

    $this->actingAs($user)
        ->postJson(route('posts.react', $post), ['type' => 'haha'])
        ->assertOk()
        ->assertJsonPath('data.likes_count', 1)
        ->assertJsonPath('data.reactions_count', 1)
        ->assertJsonPath('data.reaction_counts.paw', 0)
        ->assertJsonPath('data.reaction_counts.haha', 1)
        ->assertJsonPath('data.current_reaction', 'haha');

    $this->assertDatabaseHas('posts', [
        'id' => $post->id,
        'likes_count' => 1,
        'reactions_count' => 1,
        'paw_count' => 0,
        'haha_count' => 1,
    ]);

    $this->actingAs($user)
        ->postJson(route('posts.react', $post), ['type' => 'haha'])
        ->assertOk()
        ->assertJsonPath('data.likes_count', 0)
        ->assertJsonPath('data.reactions_count', 0)
        ->assertJsonPath('data.reaction_counts.haha', 0)
        ->assertJsonPath('data.current_reaction', null);

    $this->assertDatabaseHas('posts', [
        'id' => $post->id,
        'likes_count' => 0,
        'reactions_count' => 0,
        'paw_count' => 0,
        'haha_count' => 0,
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
        ->postJson(route('posts.react', $post), ['type' => 'angry'])
        ->assertOk()
        ->assertJsonPath('data.likes_count', 1)
        ->assertJsonPath('data.reaction_counts.angry', 1)
        ->assertJsonPath('data.current_reaction', 'angry');

    $this->assertDatabaseHas('reactions', [
        'reactable_type' => (new Post)->getMorphClass(),
        'reactable_id' => $post->getKey(),
        'user_id' => $actor->getKey(),
        'type' => 'angry',
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
        'type' => Reaction::TYPE_HAHA,
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
        'paw_count' => 42,
        'haha_count' => 42,
        'angry_count' => 42,
    ]);

    foreach ([Reaction::TYPE_LOVE, Reaction::TYPE_PAW, Reaction::TYPE_PAW, Reaction::TYPE_ANGRY] as $type) {
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
        ->and($post->paw_count)->toBe(2)
        ->and($post->haha_count)->toBe(0)
        ->and($post->angry_count)->toBe(1);
});

it('accepts all supported reaction types and rejects invalid type', function (): void {
    $user = User::factory()->create();
    $post = Post::factory()->create([
        'user_id' => User::factory()->create()->id,
        'visibility' => 'public',
    ]);

    foreach (['paw', 'love', 'haha', 'wow', 'sad', 'angry'] as $type) {
        $this->actingAs($user)
            ->postJson(route('posts.react', $post), ['type' => $type])
            ->assertOk()
            ->assertJsonPath('data.current_reaction', $type);
    }

    $this->actingAs($user)
        ->postJson(route('posts.react', $post), ['type' => 'sparkle'])
        ->assertInvalid(['type']);
});

it('normalizes legacy reaction aliases to configured canonical types', function (): void {
    $user = User::factory()->create();
    $post = Post::factory()->create([
        'user_id' => User::factory()->create()->id,
        'visibility' => 'public',
    ]);

    $this->actingAs($user)
        ->postJson(route('posts.react', $post), ['type' => 'like'])
        ->assertOk()
        ->assertJsonPath('data.current_reaction', 'paw')
        ->assertJsonPath('data.reaction_counts.paw', 1);

    $this->actingAs($user)
        ->postJson(route('posts.react', $post), ['type' => 'funny'])
        ->assertOk()
        ->assertJsonPath('data.current_reaction', 'haha')
        ->assertJsonPath('data.reaction_counts.paw', 0)
        ->assertJsonPath('data.reaction_counts.haha', 1);
});

it('loads reaction definitions from configuration', function (): void {
    expect(Reaction::defaultType())->toBe('paw')
        ->and(Reaction::types())->toBe(['paw', 'love', 'haha', 'wow', 'sad', 'angry'])
        ->and(Reaction::emojiMap())->toHaveKey('paw', '🐾')
        ->and(Reaction::labelMap())->toHaveKey('angry', 'Angry')
        ->and(Reaction::counterColumn('haha'))->toBe('haha_count');
});

it('sends reaction notification with relation-light models', function (): void {
    Cache::flush();
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
            && ! $notification->reactor->relationLoaded('posts');
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
