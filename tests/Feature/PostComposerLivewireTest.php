<?php

use App\Actions\Posts\ProcessTagsAction;
use App\Enums\PostStatus;
use App\Jobs\FeedFanOutJob;
use App\Jobs\FetchLinkPreviewMetadataJob;
use App\Jobs\MediaProcessingJob;
use App\Jobs\MentionNotificationJob;
use App\Models\Content\Post;
use App\Models\Content\PostDraft;
use App\Models\Content\PostMedia;
use App\Models\Identity\User;
use App\Models\Pets\Pet;
use App\Services\LocationAutocompleteService;
use App\Services\PostMentionService;
use App\Support\Posts\PostContentHasher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
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
        ->assertSeeHtml('x-on:post-created.window="handlePostCreated($event)"')
        ->assertSeeHtml('wire:loading.class="pointer-events-none opacity-70"')
        ->assertDontSee('@js');

    Livewire::actingAs($user)
        ->test('posts.composer', ['mode' => 'modal'])
        ->assertSet('mode', 'modal')
        ->assertSeeHtml('role="dialog"')
        ->assertSee('Close post composer');
});

it('loads the composer in edit mode with existing post state', function (): void {
    $user = User::factory()->create();
    $pet = Pet::factory()->for($user)->create(['name' => 'Miso']);
    $post = Post::factory()->for($user)->create([
        'body' => 'Original park update',
        'visibility' => Post::VISIBILITY_PRIVATE,
        'mood' => 'happy',
        'location' => 'Vilnius',
        'location_display_text' => 'Vilnius, Lithuania',
        'location_lat' => 54.6872,
        'location_lng' => 25.2797,
        'link_preview' => [
            'url' => 'https://example.com/miso',
            'title' => 'Miso update',
            'domain' => 'example.com',
        ],
        'pet_id' => $pet->id,
        'tagged_pets' => [$pet->id],
    ]);
    $post->pets()->attach($pet->id, ['is_primary' => true]);
    PostMedia::factory()->for($post, 'post')->create([
        'file_path' => 'posts/miso.jpg',
        'media_type' => 'image',
        'alt_text' => 'Miso in the grass',
        'order' => 0,
    ]);

    Livewire::actingAs($user)
        ->test('posts.composer', ['mode' => 'modal', 'editPostId' => $post->id])
        ->assertSet('isEditMode', true)
        ->assertSet('modalOpen', true)
        ->assertSet('textContent', 'Original park update')
        ->assertSet('selectedVisibility', Post::VISIBILITY_PRIVATE)
        ->assertSet('selectedMood', 'happy')
        ->assertSet('locationDisplayText', 'Vilnius, Lithuania')
        ->assertSet('locationLat', '54.6872')
        ->assertSet('locationLng', '25.2797')
        ->assertSet('selectedPetIds', [$pet->id])
        ->assertSet('attachmentMetadata.0.is_existing', true)
        ->assertSee('Edit post')
        ->assertSee('Editing post')
        ->assertSee('Save changes')
        ->assertSee('Miso')
        ->assertSee('Miso update')
        ->assertDontSee('Schedule post');
});

it('loads a quoted post preview and creates a quote post', function (): void {
    Queue::fake([FeedFanOutJob::class]);

    $author = User::factory()->create(['name' => 'Original Author']);
    $viewer = User::factory()->create();
    $original = Post::factory()->for($author)->create([
        'body' => 'A thoughtful post about neighborhood leash manners and safe greetings.',
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);
    PostMedia::factory()->for($original, 'post')->create([
        'file_path' => 'posts/quote-preview.jpg',
        'media_type' => 'image',
        'order' => 0,
    ]);

    Livewire::actingAs($viewer)
        ->test('posts.composer', ['mode' => 'modal', 'quotePostId' => $original->id])
        ->assertSet('quotePostId', $original->id)
        ->assertSee('Quote post')
        ->assertSee('Original Author')
        ->assertSee('A thoughtful post about neighborhood leash manners')
        ->set('textContent', 'Adding my own experience from the park.')
        ->call('submit')
        ->assertDispatched('post-created')
        ->assertDispatched('toast-message', message: 'Your post is live! 🐾', type: 'success');

    $quote = Post::query()
        ->where('user_id', $viewer->id)
        ->where('quote_post_id', $original->id)
        ->firstOrFail();

    expect($quote->body)->toBe('Adding my own experience from the park.')
        ->and($quote->original_post_id)->toBeNull();

    Queue::assertPushed(FeedFanOutJob::class, fn (FeedFanOutJob $job): bool => $job->postId === $quote->id);
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

it('renders the media attachment strip controls and upload behaviours', function (): void {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('posts.composer')
        ->assertSee('Attach photo or video')
        ->assertSee('Drop to attach')
        ->assertSee('Add alt text')
        ->assertSee('Up to 10 images or videos. Images 10 MB, videos 100 MB.')
        ->assertSeeHtml('accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/quicktime"')
        ->assertSeeHtml('handleFileSelection')
        ->assertSeeHtml('x-ref="attachmentStrip"')
        ->assertSeeHtml('uploadProgressOffset');
});

it('renders the visibility selector as a toolbar dropdown', function (): void {
    $user = User::factory()->create([
        'profile_visibility' => 'followers_only',
    ]);

    Livewire::actingAs($user)
        ->test('posts.composer')
        ->assertSet('selectedVisibility', Post::VISIBILITY_FOLLOWERS)
        ->assertSee('Visibility')
        ->assertSee('Choose who can see this post.')
        ->assertSee('Public')
        ->assertSee('Anyone on PetSocial can see this post.')
        ->assertSee('Followers')
        ->assertSee('People who follow you can see this post.')
        ->assertSee('Friends')
        ->assertSee('Mutual followers can see this post.')
        ->assertSee('Only me')
        ->assertSee('Only you can see this post.')
        ->assertSeeHtml('wire:click="selectVisibility(\'private\')"')
        ->assertDontSee('Only you will see this post');
});

it('renders the location tag picker controls', function (): void {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('posts.composer')
        ->assertSeeHtml('aria-label="Add location"')
        ->set('locationPickerOpen', true)
        ->assertSeeHtml('useCurrentLocation()')
        ->assertSeeHtml('placeholder="Add a location."')
        ->assertSeeHtml('wire:model.live.debounce.400ms="locationSearch"')
        ->assertSee('Searching locations...');
});

it('renders the scheduled posting picker controls', function (): void {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('posts.composer')
        ->assertSeeHtml('aria-label="Schedule post"')
        ->assertDontSeeHtml('type="datetime-local"')
        ->set('schedulePickerOpen', true)
        ->assertSeeHtml('postSchedulePicker(')
        ->assertSee('Select a date')
        ->assertSee('Select a time')
        ->assertSee('Times use your local timezone and publish in 15-minute increments.')
        ->assertSee('Apply schedule');
});

it('queues pasted link previews and hydrates the preview card from cached job results', function (): void {
    Queue::fake([FetchLinkPreviewMetadataJob::class]);

    $user = User::factory()->create();
    $component = Livewire::actingAs($user)
        ->test('posts.composer')
        ->call('queueLinkPreviewFetch', 'https://example.com/luna')
        ->assertSet('isLinkPreviewLoading', true)
        ->assertSet('detectedLinkPreviewUrl', 'https://example.com/luna')
        ->assertSeeHtml('wire:poll.2s="pollLinkPreviewResult"');

    Queue::assertPushed(FetchLinkPreviewMetadataJob::class, fn (FetchLinkPreviewMetadataJob $job): bool => $job->url === 'https://example.com/luna'
        && $job->postId === null
        && is_string($job->cacheKey));

    $requestKey = $component->get('linkPreviewRequestKey');

    Cache::put("posts:link-preview:{$user->id}:{$requestKey}", [
        'status' => 'ready',
        'url' => 'https://example.com/luna',
        'preview' => [
            'url' => 'https://example.com/luna',
            'title' => 'Luna at the park',
            'description' => 'A sunny afternoon walk',
            'image' => 'https://example.com/luna.jpg',
            'domain' => 'example.com',
        ],
    ], now()->addMinutes(10));

    $component
        ->call('pollLinkPreviewResult')
        ->assertSet('isLinkPreviewLoading', false)
        ->assertSet('linkPreviewData.title', 'Luna at the park')
        ->assertSee('Luna at the park')
        ->assertSee('A sunny afternoon walk')
        ->assertSeeHtml('aria-label="Dismiss link preview"')
        ->call('removeLinkPreview')
        ->assertSet('linkPreviewData', [])
        ->assertSet('dismissedLinkPreviewUrl', 'https://example.com/luna')
        ->call('queueLinkPreviewFetch', 'https://example.com/luna')
        ->assertSet('isLinkPreviewLoading', false);
});

it('sets and clears a scheduled publish time from the composer', function (): void {
    $user = User::factory()->create();
    $future = now('UTC')->addDay()->setTime(9, 0, 0);
    $displayText = $future->format('M j, Y \a\t g:i A');

    Livewire::actingAs($user)
        ->test('posts.composer')
        ->call(
            'setScheduledPost',
            $future->toIso8601String(),
            $displayText,
            $future->format('Y-m-d'),
            '09',
            '00',
        )
        ->assertSet('scheduledPublishAt', $future->toIso8601String())
        ->assertSet('scheduledDisplayText', $displayText)
        ->assertSet('schedulePickerOpen', false)
        ->assertSee('Scheduled for '.$displayText)
        ->assertSeeHtml('aria-label="Cancel scheduled post"')
        ->assertSee('Schedule')
        ->call('clearSchedule')
        ->assertSet('scheduledPublishAt', null)
        ->assertSet('scheduledDisplayText', null)
        ->assertDontSee('Scheduled for '.$displayText);
});

it('loads post composer location suggestions and stores selected coordinates', function (): void {
    $this->instance(LocationAutocompleteService::class, new class extends LocationAutocompleteService
    {
        /**
         * @return list<array{label: string, name: string, region: ?string, latitude: float, longitude: float}>
         */
        public function suggest(string $query, int $limit = 5): array
        {
            return [
                [
                    'label' => 'Vilnius, Lithuania',
                    'name' => 'Vilnius',
                    'region' => 'Lithuania',
                    'latitude' => 54.6872,
                    'longitude' => 25.2797,
                ],
            ];
        }
    });

    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('posts.composer')
        ->set('locationPickerOpen', true)
        ->set('locationSearch', 'Viln')
        ->assertSet('locationSuggestionsOpen', true)
        ->assertSee('Vilnius')
        ->assertSee('Lithuania')
        ->call('selectLocationSuggestion', 0)
        ->assertSet('locationDisplayText', 'Vilnius, Lithuania')
        ->assertSet('locationSearch', 'Vilnius, Lithuania')
        ->assertSet('locationLat', '54.6872')
        ->assertSet('locationLng', '25.2797')
        ->assertSet('locationSuggestionsOpen', false)
        ->assertSeeHtml('aria-label="Remove location tag"')
        ->call('removeLocationTag')
        ->assertSet('locationDisplayText', null)
        ->assertSet('locationSearch', null)
        ->assertSet('locationLat', null)
        ->assertSet('locationLng', null);
});

it('reverse geocodes browser coordinates through the server service', function (): void {
    $this->instance(LocationAutocompleteService::class, new class extends LocationAutocompleteService
    {
        /**
         * @return array{label: string, name: string, region: ?string, latitude: float, longitude: float}|null
         */
        public function reverse(float $latitude, float $longitude): ?array
        {
            expect($latitude)->toBe(51.5074)
                ->and($longitude)->toBe(-0.1278);

            return [
                'label' => 'London, United Kingdom',
                'name' => 'London',
                'region' => 'United Kingdom',
                'latitude' => $latitude,
                'longitude' => $longitude,
            ];
        }
    });

    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('posts.composer')
        ->set('locationPickerOpen', true)
        ->call('reverseGeocodeCoordinates', 51.5074, -0.1278)
        ->assertSet('locationDisplayText', 'London, United Kingdom')
        ->assertSet('locationLat', '51.5074')
        ->assertSet('locationLng', '-0.1278')
        ->assertSee('London, United Kingdom');
});

it('renders and updates the toolbar mood picker', function (): void {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('posts.composer')
        ->assertSeeHtml('aria-label="Add mood"')
        ->assertSee('Mood')
        ->assertSee('Happy')
        ->assertSee('😊')
        ->assertSee('Excited')
        ->assertSee('🎉')
        ->assertSee('Proud')
        ->assertSee('🏆')
        ->assertSee('Worried')
        ->assertSee('😟')
        ->assertSee('Sad')
        ->assertSee('😢')
        ->assertSee('Grateful')
        ->assertSee('🙏')
        ->assertSee('Playful')
        ->assertSee('🎮')
        ->assertDontSeeHtml('wire:model="selectedMood"')
        ->call('selectMood', 'playful')
        ->assertSet('selectedMood', 'playful')
        ->assertSee('feeling 🎮 playful')
        ->assertSeeHtml('aria-label="Remove mood"')
        ->call('removeMood')
        ->assertSet('selectedMood', null)
        ->assertDontSee('feeling 🎮 playful');
});

it('updates current post visibility without changing the stored account preference', function (): void {
    $user = User::factory()->create([
        'profile_visibility' => 'public',
    ]);

    Livewire::actingAs($user)
        ->test('posts.composer')
        ->assertSet('selectedVisibility', Post::VISIBILITY_PUBLIC)
        ->call('selectVisibility', Post::VISIBILITY_PRIVATE)
        ->assertSet('selectedVisibility', Post::VISIBILITY_PRIVATE)
        ->assertSee('Only you will see this post')
        ->call('selectVisibility', 'team')
        ->assertSet('selectedVisibility', Post::VISIBILITY_PRIVATE);

    expect($user->fresh()->profile_visibility)->toBe('public');
});

it('renders pet tagging as a toolbar dropdown and selected chips', function (): void {
    $user = User::factory()->create();
    $zuzu = Pet::factory()->for($user)->create([
        'name' => 'Zuzu',
        'species' => 'dog',
    ]);
    $alfie = Pet::factory()->for($user)->create([
        'name' => 'Alfie',
        'species' => 'cat',
    ]);

    Livewire::actingAs($user)
        ->test('posts.composer', ['selectedPetIds' => [$zuzu->getKey()]])
        ->assertSee('Tag a pet')
        ->assertSee('Choose one or more pets for this post.')
        ->assertSeeInOrder(['Alfie', 'Cat', 'Zuzu', 'Dog'])
        ->assertSee('Remove Zuzu tag')
        ->assertSeeHtml('wire:click="togglePetTag('.$alfie->getKey().')"');
});

it('toggles and removes tagged pets from the composer', function (): void {
    $user = User::factory()->create();
    $firstPet = Pet::factory()->for($user)->create();
    $secondPet = Pet::factory()->for($user)->create();

    Livewire::actingAs($user)
        ->test('posts.composer')
        ->call('togglePetTag', $firstPet->getKey())
        ->assertSet('selectedPetIds', [$firstPet->getKey()])
        ->call('togglePetTag', $secondPet->getKey())
        ->assertSet('selectedPetIds', [$firstPet->getKey(), $secondPet->getKey()])
        ->call('removePetTag', $firstPet->getKey())
        ->assertSet('selectedPetIds', [$secondPet->getKey()]);
});

it('locks pet tags when composing from a pet profile context', function (): void {
    $user = User::factory()->create();
    $profilePet = Pet::factory()->for($user)->create(['name' => 'Miso']);
    $otherPet = Pet::factory()->for($user)->create(['name' => 'Nori']);

    Livewire::actingAs($user)
        ->test('posts.composer', [
            'contextType' => 'pet-profile',
            'contextId' => $profilePet->getKey(),
        ])
        ->assertSet('petTaggingLocked', true)
        ->assertSet('selectedPetIds', [$profilePet->getKey()])
        ->assertSee('Miso')
        ->assertDontSee('Tag a pet')
        ->assertDontSee('Remove Miso tag')
        ->call('removePetTag', $profilePet->getKey())
        ->assertSet('selectedPetIds', [$profilePet->getKey()])
        ->call('togglePetTag', $otherPet->getKey())
        ->assertSet('selectedPetIds', [$profilePet->getKey()]);
});

it('loads sortable js for attachment reordering', function (): void {
    $javascript = (string) file_get_contents(resource_path('js/app.js'));

    expect($javascript)
        ->toContain('cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js')
        ->toContain('Sortable.create')
        ->toContain('applyAttachmentOrder')
        ->toContain('maybeAutosaveDraft')
        ->toContain('window.setInterval');
});

it('tracks uploaded attachment metadata, alt text, removal, and ordering by client id', function (): void {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('posts.composer')
        ->call('registerUploadedAttachment', 'client-a', 'mediaUploadSlot0', 'livewire-tmp/a', [
            'file_name' => 'first.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 1024,
            'order' => 0,
        ])
        ->call('registerUploadedAttachment', 'client-b', 'mediaUploadSlot1', 'livewire-tmp/b', [
            'file_name' => 'second.mp4',
            'mime_type' => 'video/mp4',
            'file_size' => 2048,
            'order' => 1,
        ])
        ->call('updateAttachmentAltText', 'client-a', 'A terrier at the park')
        ->call('reorderAttachments', ['client-b', 'client-a'])
        ->assertSet('attachmentMetadata.0.client_id', 'client-b')
        ->assertSet('attachmentMetadata.0.media_type', 'video')
        ->assertSet('attachmentMetadata.0.order', 0)
        ->assertSet('attachmentMetadata.1.client_id', 'client-a')
        ->assertSet('attachmentMetadata.1.alt_text', 'A terrier at the park')
        ->call('removeAttachment', 'client-b')
        ->assertSet('attachmentMetadata.0.client_id', 'client-a')
        ->assertSet('temporaryFilePaths.0', 'livewire-tmp/a');
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
        ->assertDispatched('post-created')
        ->assertDispatched('toast-message', message: 'Your post is live! 🐾', type: 'success');

    $post = Post::query()->firstOrFail();

    expect($post->body)->toBe('Sunny park update for #dogs')
        ->and($post->visibility)->toBe(Post::VISIBILITY_PUBLIC)
        ->and($post->mood)->toBe('happy')
        ->and($post->location_display_text)->toBe('Neighborhood park')
        ->and($post->pets()->whereKey($pet->getKey())->exists())->toBeTrue();

    Queue::assertPushed(FeedFanOutJob::class);
});

it('updates an existing post through the edit composer and notifies only new mentions', function (): void {
    $user = User::factory()->create();
    $previousMention = User::factory()->create(['username' => 'old_friend']);
    $newMention = User::factory()->create(['username' => 'new_friend']);
    $oldPet = Pet::factory()->for($user)->create(['name' => 'Old Pet', 'posts_count' => 1]);
    $newPet = Pet::factory()->for($user)->create(['name' => 'New Pet', 'posts_count' => 0]);
    $post = Post::factory()->for($user)->create([
        'body' => 'Original update for @old_friend #OldTag',
        'body_html' => 'Original update for @old_friend #OldTag',
        'visibility' => Post::VISIBILITY_PUBLIC,
        'mood' => 'happy',
        'location' => 'Old park',
        'location_display_text' => 'Old park',
        'pet_id' => $oldPet->id,
        'tagged_pets' => [$oldPet->id],
        'created_at' => now()->subHour(),
    ]);
    $post->pets()->attach($oldPet->id, ['is_primary' => true]);
    app(ProcessTagsAction::class)->handle($post);
    app(PostMentionService::class)->sync($post, $user, false);

    Queue::fake([MentionNotificationJob::class]);

    Livewire::actingAs($user)
        ->test('posts.composer', ['mode' => 'modal', 'editPostId' => $post->id])
        ->set('textContent', 'Updated update for @new_friend #NewTag')
        ->set('selectedVisibility', Post::VISIBILITY_FOLLOWERS)
        ->set('selectedMood', 'playful')
        ->set('locationDisplayText', 'New park')
        ->set('locationLat', '51.5074')
        ->set('locationLng', '-0.1278')
        ->set('selectedPetIds', [$newPet->id])
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('modalOpen', false)
        ->assertDispatched('post-updated', postId: $post->id)
        ->assertDispatched('toast-message', message: 'Post updated.', type: 'success');

    $post->refresh();

    expect($post->body)->toBe('Updated update for @new_friend #NewTag')
        ->and($post->visibility)->toBe(Post::VISIBILITY_FOLLOWERS)
        ->and($post->mood)->toBe('playful')
        ->and($post->location_display_text)->toBe('New park')
        ->and((string) $post->location_lat)->toStartWith('51.5074')
        ->and((string) $post->location_lng)->toStartWith('-0.1278')
        ->and($post->edited_at)->not->toBeNull()
        ->and($post->edit_count)->toBe(1)
        ->and($post->pets()->whereKey($oldPet->id)->exists())->toBeFalse()
        ->and($post->pets()->whereKey($newPet->id)->exists())->toBeTrue()
        ->and($oldPet->fresh()->posts_count)->toBe(0)
        ->and($newPet->fresh()->posts_count)->toBe(1);

    $this->assertDatabaseMissing('post_mentions', [
        'post_id' => $post->id,
        'mentioned_user_id' => $previousMention->id,
    ]);
    $this->assertDatabaseHas('post_mentions', [
        'post_id' => $post->id,
        'mentioned_user_id' => $newMention->id,
        'mentioned_username' => 'new_friend',
    ]);
    $this->assertDatabaseHas('hashtags', ['normalized_name' => 'newtag']);
    $this->assertDatabaseHas('hashtags', ['normalized_name' => 'oldtag', 'posts_count' => 0]);

    Queue::assertPushed(MentionNotificationJob::class, fn (MentionNotificationJob $job): bool => $job->mentionedUserId === $newMention->id
        && $job->postId === $post->id);
    Queue::assertNotPushed(MentionNotificationJob::class, fn (MentionNotificationJob $job): bool => $job->mentionedUserId === $previousMention->id);
});

it('creates scheduled posts without immediate feed fanout', function (): void {
    Queue::fake([FeedFanOutJob::class]);

    $user = User::factory()->create();
    $future = now('UTC')->addDay()->setTime(10, 15, 0);

    Livewire::actingAs($user)
        ->test('posts.composer')
        ->set('textContent', 'Tomorrow morning update')
        ->call(
            'setScheduledPost',
            $future->toIso8601String(),
            $future->format('M j, Y \a\t g:i A'),
            $future->format('Y-m-d'),
            '10',
            '15',
        )
        ->call('submit')
        ->assertHasNoErrors()
        ->assertDispatched('post-created')
        ->assertDispatched('toast-message', message: 'Post scheduled for '.$future->format('M j, Y \a\t g:i A').' ✓', type: 'success');

    $post = Post::query()->firstOrFail();

    expect($post->body)->toBe('Tomorrow morning update')
        ->and($post->status)->toBe(PostStatus::Scheduled)
        ->and($post->scheduled_publish_at?->toIso8601String())->toBe($future->toIso8601String());

    Queue::assertNotPushed(FeedFanOutJob::class);
});

it('passes ordered temporary media attachments to the creation pipeline', function (): void {
    Queue::fake();

    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('posts.composer')
        ->set('textContent', 'Mixed media day')
        ->set('attachmentMetadata', [
            [
                'client_id' => 'client-image',
                'slot' => 'mediaUploadSlot0',
                'temporary_path' => 'livewire-tmp/image',
                'preview_data_url' => null,
                'file_name' => 'image.jpg',
                'media_type' => 'image',
                'mime_type' => 'image/jpeg',
                'file_size' => 1024,
                'alt_text' => 'A good image',
                'order' => 1,
            ],
            [
                'client_id' => 'client-video',
                'slot' => 'mediaUploadSlot1',
                'temporary_path' => 'livewire-tmp/video',
                'preview_data_url' => null,
                'file_name' => 'video.mp4',
                'media_type' => 'video',
                'mime_type' => 'video/mp4',
                'file_size' => 2048,
                'alt_text' => null,
                'order' => 0,
            ],
        ])
        ->call('submit')
        ->assertHasNoErrors()
        ->assertDispatched('post-created')
        ->assertDispatched('toast-message', message: 'Your post is live! 🐾', type: 'success');

    $post = Post::query()->firstOrFail();

    expect($post->type)->toBe(Post::TYPE_VIDEO);

    Queue::assertPushed(MediaProcessingJob::class, fn (MediaProcessingJob $job): bool => $job->temporaryPath === 'livewire-tmp/video' && $job->order === 0);
    Queue::assertPushed(MediaProcessingJob::class, fn (MediaProcessingJob $job): bool => $job->temporaryPath === 'livewire-tmp/image' && $job->altText === 'A good image' && $job->order === 1);
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
        ->assertSee('Possible duplicate post')
        ->assertSee('This looks very similar to something you posted recently. Are you sure you want to post it again?')
        ->assertSee('Post anyway')
        ->assertSee('Go back')
        ->assertDispatched('post-duplicate-detected')
        ->call('goBackFromDuplicate')
        ->assertSet('duplicateDetected', false)
        ->assertSet('confirmedDuplicate', false)
        ->assertSet('textContent', '  a    duplicate story  ');

    expect(Post::query()->count())->toBe(1);
});

it('posts anyway after duplicate confirmation and closes modal composer with success feedback', function (): void {
    Queue::fake();

    $user = User::factory()->create();

    Post::factory()->for($user)->create([
        'body' => 'Repeat this moment',
        'content_hash' => app(PostContentHasher::class)->hash('Repeat this moment'),
        'author_type' => $user::class,
        'author_id' => $user->getKey(),
        'created_at' => now(),
    ]);

    Livewire::actingAs($user)
        ->test('posts.composer', ['mode' => 'modal'])
        ->set('textContent', 'Repeat this moment')
        ->call('submit')
        ->assertSet('duplicateDetected', true)
        ->call('confirmDuplicateAndSubmit')
        ->assertHasNoErrors()
        ->assertSet('modalOpen', false)
        ->assertSet('textContent', '')
        ->assertDispatched('post-created')
        ->assertDispatched('toast-message', message: 'Your post is live! 🐾', type: 'success');

    expect(Post::query()->count())->toBe(2);
});

it('keeps composer content visible and scrolls to validation errors after a failed submission', function (): void {
    $user = User::factory()->create();
    $body = str_repeat('a', 1001);

    Livewire::actingAs($user)
        ->test('posts.composer')
        ->set('textContent', $body)
        ->call('submit')
        ->assertHasErrors(['body'])
        ->assertSet('textContent', $body)
        ->assertSet('isSubmitting', false)
        ->assertSeeHtml('data-composer-error')
        ->assertDispatched('post-submission-failed');

    expect(Post::query()->count())->toBe(0);
});

it('renders feed listeners for optimistic published post prepending', function (): void {
    $user = User::factory()->create();

    $this
        ->actingAs($user)
        ->get(route('feed.index'))
        ->assertOk()
        ->assertSeeHtml('x-data="feedPostList()"')
        ->assertSeeHtml('x-on:post-created.window="prependPost($event)"')
        ->assertSee('New');
});

it('renders post card editing actions only during the edit window', function (): void {
    $user = User::factory()->create();
    $recentPost = Post::factory()->for($user)->create([
        'created_at' => now()->subHours(2),
    ]);
    $expiredPost = Post::factory()->for($user)->create([
        'created_at' => now()->subHours(25),
    ]);
    $editedPost = Post::factory()->for($user)->create([
        'created_at' => now()->subHours(2),
        'edited_at' => now()->subMinutes(10),
    ]);

    $this->actingAs($user);

    $recentHtml = Blade::render('<x-post-card :post="$post" />', ['post' => $recentPost]);
    $expiredHtml = Blade::render('<x-post-card :post="$post" />', ['post' => $expiredPost]);
    $editedHtml = Blade::render('<x-post-card :post="$post" />', ['post' => $editedPost]);

    expect($recentHtml)
        ->toContain('data-ui="post-card-menu-edit"')
        ->toContain('Edit post')
        ->and($expiredHtml)
        ->toContain('data-ui="post-card-menu-edit-disabled"')
        ->toContain('Cannot edit — posts can only be edited within 24 hours of creation')
        ->and($editedHtml)
        ->toContain('Edited')
        ->toContain('title="Edited ');
});

it('renders quote and repost blocks inside shared post cards', function (): void {
    $author = User::factory()->create(['name' => 'Original Card Author']);
    $viewer = User::factory()->create();
    $original = Post::factory()->for($author)->create([
        'body' => 'Original card text that should appear inside the embedded block.',
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);
    PostMedia::factory()->for($original, 'post')->create([
        'file_path' => 'posts/card-quote-preview.jpg',
        'media_type' => 'image',
        'order' => 0,
    ]);
    $quote = Post::factory()->for($viewer)->create([
        'body' => 'My quote commentary',
        'quote_post_id' => $original->id,
    ]);
    $repost = Post::factory()->for($viewer)->create([
        'body' => '',
        'original_post_id' => $original->id,
    ]);

    $quote->load(['quotePost.author.media', 'quotePost.postMedia']);
    $repost->load(['originalPost.author.media', 'originalPost.postMedia']);

    $this->actingAs($viewer);

    $quoteHtml = Blade::render('<x-post-card :post="$post" />', ['post' => $quote]);
    $repostHtml = Blade::render('<x-post-card :post="$post" />', ['post' => $repost]);

    expect($quoteHtml)
        ->toContain('Quote post')
        ->toContain('Original Card Author')
        ->toContain('Original card text')
        ->toContain('card-quote-preview.jpg')
        ->and($repostHtml)
        ->toContain('Repost')
        ->toContain('Original Card Author')
        ->toContain('Original card text');
});

it('opens the edit composer from the post card edit trigger', function (): void {
    $user = User::factory()->create();
    $post = Post::factory()->for($user)->create([
        'body' => 'Editable from the card',
        'created_at' => now()->subHour(),
    ]);

    Livewire::actingAs($user)
        ->test('posts.edit-trigger', ['post' => $post])
        ->assertSet('open', false)
        ->assertSee('Edit post')
        ->call('open')
        ->assertSet('open', true)
        ->assertSee('Editable from the card')
        ->assertSee('Editing post');
});

it('shows a resumable draft banner without restoring the draft automatically', function (): void {
    $user = User::factory()->create();

    $draft = PostDraft::factory()->for($user)->create([
        'context_type' => 'feed',
        'context_id' => 0,
        'body' => 'Saved thought',
        'visibility' => Post::VISIBILITY_PRIVATE,
        'tagged_pets' => [],
        'state' => [
            'text_content' => 'Saved thought',
            'temporary_file_paths' => [],
            'attachment_metadata' => [],
            'selected_pet_ids' => [],
            'location_display_text' => null,
            'location_lat' => null,
            'location_lng' => null,
            'selected_mood' => null,
            'selected_visibility' => Post::VISIBILITY_PRIVATE,
            'scheduled_publish_at' => null,
            'link_preview' => [],
            'context_type' => 'feed',
            'context_id' => 0,
        ],
    ]);

    Livewire::actingAs($user)
        ->test('posts.composer', ['contextType' => 'feed'])
        ->assertSet('draftId', null)
        ->assertSet('pendingDraftAvailable', true)
        ->assertSet('pendingDraftId', $draft->getKey())
        ->assertSet('textContent', '')
        ->assertSee('You have an unsaved draft from')
        ->assertSee('Resume draft')
        ->assertSee('Discard')
        ->call('resumeDraft')
        ->assertSet('pendingDraftAvailable', false)
        ->assertSet('draftId', $draft->getKey())
        ->assertSet('textContent', 'Saved thought')
        ->assertSet('selectedVisibility', Post::VISIBILITY_PRIVATE)
        ->assertSet('hasUnsavedChanges', false)
        ->assertDispatched('post-draft-resumed')
        ->set('textContent', 'Updated draft thought')
        ->set('hasUnsavedChanges', true)
        ->call('autosaveDraft')
        ->assertSet('hasUnsavedChanges', false)
        ->assertDispatched('post-draft-autosaved');

    $draft->refresh();

    expect($draft->body)->toBe('Updated draft thought')
        ->and($draft->state['text_content'])->toBe('Updated draft thought');
});

it('discards pending and active drafts from the composer', function (): void {
    $user = User::factory()->create();

    PostDraft::factory()->for($user)->create([
        'body' => 'Discard me',
        'state' => [
            'text_content' => 'Discard me',
            'selected_visibility' => Post::VISIBILITY_PUBLIC,
        ],
    ]);

    Livewire::actingAs($user)
        ->test('posts.composer')
        ->assertSet('pendingDraftAvailable', true)
        ->call('discardDraft')
        ->assertSet('pendingDraftAvailable', false)
        ->assertSet('draftId', null);

    expect(PostDraft::query()->count())->toBe(0);
});

it('confirms composer cancellation before clearing an unsaved draft', function (): void {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('posts.composer', ['mode' => 'modal'])
        ->set('textContent', 'An unfinished update')
        ->set('hasUnsavedChanges', true)
        ->call('autosaveDraft')
        ->assertSet('draftId', fn (?int $draftId): bool => $draftId !== null)
        ->call('requestCancel')
        ->assertSet('discardConfirmOpen', true)
        ->assertSee('Discard this post? Your unsaved draft will be lost.')
        ->call('keepEditing')
        ->assertSet('discardConfirmOpen', false)
        ->call('requestCancel')
        ->call('confirmDiscard')
        ->assertSet('textContent', '')
        ->assertSet('draftId', null)
        ->assertSet('modalOpen', false)
        ->assertDispatched('post-composer-reset');

    expect(PostDraft::query()->count())->toBe(0);
});
