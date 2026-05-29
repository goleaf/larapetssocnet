<?php

use App\Enums\PostStatus;
use App\Jobs\FeedFanOutJob;
use App\Jobs\MediaProcessingJob;
use App\Models\Content\Post;
use App\Models\Content\PostDraft;
use App\Models\Identity\User;
use App\Models\Pets\Pet;
use App\Services\LocationAutocompleteService;
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
        ->toContain('applyAttachmentOrder');
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
        ->assertDispatched('post-created');

    $post = Post::query()->firstOrFail();

    expect($post->body)->toBe('Sunny park update for #dogs')
        ->and($post->visibility)->toBe(Post::VISIBILITY_PUBLIC)
        ->and($post->mood)->toBe('happy')
        ->and($post->location_display_text)->toBe('Neighborhood park')
        ->and($post->pets()->whereKey($pet->getKey())->exists())->toBeTrue();

    Queue::assertPushed(FeedFanOutJob::class);
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
        ->assertDispatched('post-created');

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
        ->assertDispatched('post-created');

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
