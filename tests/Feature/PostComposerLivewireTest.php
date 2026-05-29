<?php

use App\Jobs\FeedFanOutJob;
use App\Models\Content\Post;
use App\Models\Content\PostDraft;
use App\Models\Identity\User;
use App\Models\Pets\Pet;
use App\Support\Posts\PostContentHasher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('renders the reusable composer in inline and modal modes', function (): void {
    $user = User::factory()->create([
        'profile_visibility' => 'followers_only',
    ]);

    Livewire::actingAs($user)
        ->test('posts.composer', ['mode' => 'inline'])
        ->assertSet('mode', 'inline')
        ->assertSet('selectedVisibility', Post::VISIBILITY_FOLLOWERS)
        ->assertSee('Create a post')
        ->assertSeeHtml('contenteditable="true"')
        ->assertSeeHtml('postComposer(')
        ->assertDontSee('@js');

    Livewire::actingAs($user)
        ->test('posts.composer', ['mode' => 'modal'])
        ->assertSet('mode', 'modal')
        ->assertSeeHtml('role="dialog"')
        ->assertSee('Close post composer');
});

it('keeps text state and exposes the computed character count', function (): void {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('posts.composer')
        ->set('textContent', str_repeat('a', 820))
        ->assertSet('textContent', str_repeat('a', 820))
        ->assertSee('Current character count: 820')
        ->assertSeeHtml('stroke-dashoffset')
        ->assertSeeHtml('x-show="showCharacterCounter"');
});

it('creates a post through the action pipeline', function (): void {
    Queue::fake();

    $user = User::factory()->create();
    $pet = Pet::factory()->for($user)->create();

    Livewire::actingAs($user)
        ->test('posts.composer', ['selectedPetIds' => [$pet->getKey()]])
        ->set('textContent', 'Sunny park update for #dogs')
        ->set('selectedMood', 'happy')
        ->set('locationDisplayText', 'Neighborhood park')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertDispatched('post-created');

    $post = Post::query()->firstOrFail();

    expect($post->body)->toBe('Sunny park update for #dogs')
        ->and($post->visibility)->toBe(Post::VISIBILITY_PUBLIC)
        ->and($post->mood)->toBe('happy')
        ->and($post->location_display_text)->toBe('Neighborhood park')
        ->and($post->pets()->whereKey($pet->getKey())->exists())->toBeTrue();

    Queue::assertPushed(FeedFanOutJob::class);
});

it('returns a duplicate warning without creating another post', function (): void {
    $user = User::factory()->create();

    Post::factory()->for($user)->create([
        'body' => 'A duplicate story',
        'content_hash' => app(PostContentHasher::class)->hash('A duplicate story'),
        'author_type' => $user::class,
        'author_id' => $user->getKey(),
        'created_at' => now(),
    ]);

    Livewire::actingAs($user)
        ->test('posts.composer')
        ->set('textContent', '  a    duplicate story  ')
        ->call('submit')
        ->assertSet('duplicateDetected', true)
        ->assertDispatched('post-duplicate-detected');

    expect(Post::query()->count())->toBe(1);
});

it('restores and autosaves composer drafts by context', function (): void {
    $user = User::factory()->create();

    $draft = PostDraft::factory()->for($user)->create([
        'context_type' => 'feed',
        'context_id' => 0,
        'body' => 'Saved thought',
        'visibility' => Post::VISIBILITY_PRIVATE,
        'tagged_pets' => [],
    ]);

    Livewire::actingAs($user)
        ->test('posts.composer', ['contextType' => 'feed'])
        ->assertSet('draftId', $draft->getKey())
        ->assertSet('textContent', 'Saved thought')
        ->assertSet('selectedVisibility', Post::VISIBILITY_PRIVATE)
        ->set('textContent', 'Updated draft thought')
        ->call('autosaveDraft')
        ->assertDispatched('post-draft-autosaved');

    expect($draft->fresh()->body)->toBe('Updated draft thought');
});
