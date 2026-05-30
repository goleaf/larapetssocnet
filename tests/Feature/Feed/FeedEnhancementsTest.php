<?php

use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Models\Pets\Pet;
use App\Models\Social\FeedMute;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('persists the best ranking preference and ranks posts by engagement signals', function (): void {
    $viewer = User::factory()->create();

    $recentPost = Post::factory()->for($viewer)->create([
        'body' => 'recent low engagement post',
        'created_at' => now()->subMinutes(2),
        'reactions_count' => 0,
        'likes_count' => 0,
        'comments_count' => 0,
    ]);

    $engagedPost = Post::factory()->for($viewer)->create([
        'body' => 'older high engagement post',
        'created_at' => now()->subDay(),
        'reactions_count' => 800,
        'likes_count' => 800,
        'comments_count' => 500,
        'type' => Post::TYPE_PHOTO,
    ]);

    Livewire::actingAs($viewer)
        ->test('feed.stream')
        ->assertSet('postIds.0', $recentPost->getKey())
        ->call('setRanking', User::FEED_RANKING_BEST)
        ->assertSet('ranking', User::FEED_RANKING_BEST)
        ->assertSet('postIds.0', $engagedPost->getKey());

    expect($viewer->fresh()->feed_ranking_preference)->toBe(User::FEED_RANKING_BEST);
});

it('uses the stored ranking preference when the URL does not specify a ranking', function (): void {
    $viewer = User::factory()->create([
        'feed_ranking_preference' => User::FEED_RANKING_BEST,
    ]);

    Livewire::actingAs($viewer)
        ->test('feed.stream')
        ->assertSet('ranking', User::FEED_RANKING_BEST);
});

it('remembers the last loaded post and can jump back to the latest feed', function (): void {
    $viewer = User::factory()->create();

    $posts = Post::factory()
        ->count(20)
        ->sequence(fn (Sequence $sequence): array => [
            'body' => 'read-position-post-'.$sequence->index,
            'created_at' => now()->subMinutes($sequence->index),
        ])
        ->create(['user_id' => $viewer->getKey()])
        ->sortByDesc('created_at')
        ->values();

    Livewire::actingAs($viewer)
        ->test('feed.stream')
        ->call('loadMore')
        ->assertSet('postIds', $posts->pluck('id')->all());

    expect(session('feed.last_seen_post_id'))->toBe($posts->last()->getKey());

    Livewire::actingAs($viewer)
        ->test('feed.stream')
        ->assertSet('restoredReadPosition', true)
        ->assertSee(__('feed.jump_to_latest'))
        ->call('jumpToLatest')
        ->assertSet('restoredReadPosition', false)
        ->assertSet('postIds', $posts->take(15)->pluck('id')->all());

    expect(session()->has('feed.last_seen_post_id'))->toBeFalse();
});

it('mutes followed users without removing the follow relationship', function (): void {
    $viewer = User::factory()->create();
    $author = User::factory()->create(['username' => 'muted_author']);

    $viewer->following()->attach($author->getKey(), ['status' => 'accepted']);

    Post::factory()->for($author)->create([
        'body' => 'post hidden after author mute',
        'created_at' => now(),
    ]);

    Livewire::actingAs($viewer)
        ->test('feed.stream')
        ->assertSee('post hidden after author mute');

    $this->actingAs($viewer)
        ->from(route('feed.index'))
        ->post(route('feed.mutes.store'), [
            'mutable_type' => 'user',
            'mutable_id' => $author->getKey(),
        ])
        ->assertRedirect(route('feed.index'));

    $this->assertDatabaseHas('feed_mutes', [
        'user_id' => $viewer->getKey(),
        'mutable_type' => (new User)->getMorphClass(),
        'mutable_id' => $author->getKey(),
    ]);

    expect($viewer->following()->whereKey($author->getKey())->exists())->toBeTrue();

    Livewire::actingAs($viewer)
        ->test('feed.stream')
        ->assertDontSee('post hidden after author mute');
});

it('mutes followed pets without removing the pet follow relationship', function (): void {
    $viewer = User::factory()->create();
    $owner = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create(['name' => 'Miso']);

    $viewer->followedPets()->attach($pet->getKey());

    $post = Post::factory()->for($owner)->create([
        'body' => 'post hidden after pet mute',
        'created_at' => now(),
    ]);
    $post->pets()->attach($pet->getKey());

    Livewire::actingAs($viewer)
        ->test('feed.stream')
        ->assertSee('post hidden after pet mute');

    $this->actingAs($viewer)
        ->from(route('feed.index'))
        ->post(route('feed.mutes.store'), [
            'mutable_type' => 'pet',
            'mutable_id' => $pet->getKey(),
        ])
        ->assertRedirect(route('feed.index'));

    $this->assertDatabaseHas('feed_mutes', [
        'user_id' => $viewer->getKey(),
        'mutable_type' => (new Pet)->getMorphClass(),
        'mutable_id' => $pet->getKey(),
    ]);

    expect($viewer->followedPets()->whereKey($pet->getKey())->exists())->toBeTrue();

    Livewire::actingAs($viewer)
        ->test('feed.stream')
        ->assertDontSee('post hidden after pet mute');
});

it('shows contextual empty feed suggestions from matching pet species', function (): void {
    $viewer = User::factory()->create();
    Pet::factory()->for($viewer)->create(['species' => 'dog']);

    $dogOwner = User::factory()->create([
        'name' => 'Dog Friend',
        'followers_count' => 12,
    ]);
    Pet::factory()->for($dogOwner)->create(['species' => 'dog']);

    $catOwner = User::factory()->create([
        'name' => 'Cat Friend',
        'followers_count' => 99,
    ]);
    Pet::factory()->for($catOwner)->create(['species' => 'cat']);

    Livewire::actingAs($viewer)
        ->test('feed.stream')
        ->assertSee(__('feed.empty_contextual_title'))
        ->assertSee('Dog Friend')
        ->assertDontSee('Cat Friend');
});

it('renders a desktop hover preview trigger for long post text', function (): void {
    $viewer = User::factory()->create();
    $body = str_repeat('A long feed post sentence with enough detail. ', 12);

    Post::factory()->for($viewer)->create([
        'body' => $body,
        'body_html' => '',
    ]);

    Livewire::actingAs($viewer)
        ->test('feed.stream')
        ->assertSee('See more')
        ->assertSee('line-clamp-5', false)
        ->assertSee('A long feed post sentence with enough detail')
        ->assertSee('role="tooltip"', false);
});

it('unmutes accounts from the muted settings page', function (): void {
    $viewer = User::factory()->create();
    $author = User::factory()->create(['username' => 'settings_mute']);

    $mute = FeedMute::query()->create([
        'user_id' => $viewer->getKey(),
        'mutable_type' => $author->getMorphClass(),
        'mutable_id' => $author->getKey(),
    ]);

    $this->actingAs($viewer)
        ->get(route('settings.muted'))
        ->assertOk()
        ->assertSee('@settings_mute');

    $this->actingAs($viewer)
        ->from(route('settings.muted'))
        ->delete(route('feed.mutes.destroy', $mute))
        ->assertRedirect(route('settings.muted'));

    $this->assertDatabaseMissing('feed_mutes', [
        'id' => $mute->getKey(),
    ]);
});
