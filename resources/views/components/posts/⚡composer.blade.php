<?php

use App\Actions\Posts\CreatePostAction;
use App\Enums\PostStatus;
use App\Jobs\FetchLinkPreviewMetadataJob;
use App\Models\Content\Post;
use App\Models\Content\PostDraft;
use App\Models\Identity\User;
use App\Models\Pets\Pet;
use App\Models\Pets\Species;
use App\Services\LocationAutocompleteService;
use App\Services\PostDraftService;
use App\Services\PostMetadataService;
use App\Support\Posts\PostMood;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new class extends Component
{
    use AuthorizesRequests;
    use WithFileUploads;

    private const MODE_INLINE = 'inline';

    private const MODE_MODAL = 'modal';

    private const MAX_ATTACHMENTS = 10;

    private const MEDIA_UPLOAD_SLOTS = [
        'mediaUploadSlot0',
        'mediaUploadSlot1',
        'mediaUploadSlot2',
        'mediaUploadSlot3',
        'mediaUploadSlot4',
        'mediaUploadSlot5',
        'mediaUploadSlot6',
        'mediaUploadSlot7',
        'mediaUploadSlot8',
        'mediaUploadSlot9',
    ];

    public string $mode = self::MODE_INLINE;

    public bool $modalOpen = true;

    public string $textContent = '';

    /**
     * @var list<TemporaryUploadedFile>
     */
    public array $mediaUploads = [];

    /**
     * @var list<string>
     */
    public array $temporaryFilePaths = [];

    /**
     * @var list<array{client_id: string, slot: ?string, temporary_path: string, preview_data_url: ?string, file_name: string, media_type: string, mime_type: ?string, file_size: int, alt_text: ?string, order: int}>
     */
    public array $attachmentMetadata = [];

    public mixed $mediaUploadSlot0 = null;

    public mixed $mediaUploadSlot1 = null;

    public mixed $mediaUploadSlot2 = null;

    public mixed $mediaUploadSlot3 = null;

    public mixed $mediaUploadSlot4 = null;

    public mixed $mediaUploadSlot5 = null;

    public mixed $mediaUploadSlot6 = null;

    public mixed $mediaUploadSlot7 = null;

    public mixed $mediaUploadSlot8 = null;

    public mixed $mediaUploadSlot9 = null;

    /**
     * @var list<string>
     */
    public array $mediaErrors = [];

    /**
     * @var list<int>
     */
    public array $selectedPetIds = [];

    public ?string $locationDisplayText = null;

    public ?string $locationSearch = null;

    public ?string $locationLat = null;

    public ?string $locationLng = null;

    /**
     * @var list<array{label: string, name?: string, region?: string|null, latitude: float, longitude: float}>
     */
    public array $locationSuggestions = [];

    public bool $locationPickerOpen = false;

    public bool $locationSuggestionsOpen = false;

    public ?string $selectedMood = null;

    public string $selectedVisibility = Post::VISIBILITY_PUBLIC;

    public ?string $scheduledPublishAt = null;

    public ?string $scheduledDisplayText = null;

    public ?string $scheduledDate = null;

    public ?string $scheduledHour = null;

    public ?string $scheduledMinute = null;

    public bool $schedulePickerOpen = false;

    /**
     * @var array<string, mixed>
     */
    public array $linkPreviewData = [];

    public ?string $detectedLinkPreviewUrl = null;

    public ?string $linkPreviewRequestKey = null;

    public ?string $dismissedLinkPreviewUrl = null;

    public ?int $draftId = null;

    public bool $isUploading = false;

    public bool $isSubmitting = false;

    public bool $isAutoSavingDraft = false;

    public bool $isLinkPreviewLoading = false;

    public bool $duplicateDetected = false;

    public ?int $duplicatePostId = null;

    public bool $confirmedDuplicate = false;

    public string $contextType = 'default';

    public int $contextId = 0;

    public ?int $fixedPetId = null;

    public bool $petTaggingLocked = false;

    /**
     * @param  list<int>|null  $selectedPetIds
     */
    public function mount(
        string $mode = self::MODE_INLINE,
        ?array $selectedPetIds = null,
        ?string $visibility = null,
        ?string $contextType = null,
        int $contextId = 0,
        ?int $fixedPetId = null,
        bool $lockPetTags = false,
    ): void {
        $this->mode = in_array($mode, [self::MODE_INLINE, self::MODE_MODAL], true) ? $mode : self::MODE_INLINE;
        $this->contextType = filled($contextType) ? (string) $contextType : 'default';
        $this->contextId = max(0, $contextId);
        $this->selectedVisibility = $this->normalizeVisibility($visibility) ?? $this->defaultVisibility();
        $initialPetIds = $this->normalizePetIds($selectedPetIds ?? []);
        $contextFixedPetId = in_array($this->contextType, ['pet', 'pet-profile', 'pet_profile'], true) && $this->contextId > 0
            ? $this->contextId
            : null;

        $this->fixedPetId = $fixedPetId && $fixedPetId > 0
            ? $fixedPetId
            : ($contextFixedPetId ?? ($lockPetTags ? ($initialPetIds[0] ?? null) : null));
        $this->petTaggingLocked = $lockPetTags || $this->fixedPetId !== null;
        $this->selectedPetIds = $this->fixedPetId !== null ? [$this->fixedPetId] : $initialPetIds;

        $this->restoreDraft();
        $this->enforceFixedPetTag();
    }

    #[Computed]
    public function characterCount(): int
    {
        return mb_strlen($this->textContent);
    }

    #[Computed]
    public function availablePets(): Collection
    {
        $user = $this->viewer();

        if (! $user instanceof User) {
            return collect();
        }

        return Pet::query()
            ->without(['user', 'breed', 'tags'])
            ->with(['media', 'species'])
            ->select(['id', 'user_id', 'name', 'species', 'species_id', 'avatar_path'])
            ->where('user_id', $user->getKey())
            ->orWhereIn('id', function ($query) use ($user): void {
                $query
                    ->select('pet_id')
                    ->from('pet_owners')
                    ->where('user_id', $user->getKey())
                    ->whereNotNull('accepted_at')
                    ->where(function ($permissionQuery): void {
                        $permissionQuery
                            ->where('can_post', true)
                            ->orWhereIn('role', ['owner', 'admin', 'poster']);
                    });
            })
            ->orderBy('name')
            ->get();
    }

    public function togglePetTag(int $petId): void
    {
        if ($this->petTaggingLocked || ! $this->availablePets()->contains('id', $petId)) {
            return;
        }

        $selectedPetIds = collect($this->selectedPetIds);

        $this->selectedPetIds = $selectedPetIds->contains($petId)
            ? $selectedPetIds->reject(fn (int $selectedPetId): bool => $selectedPetId === $petId)->values()->all()
            : $selectedPetIds->push($petId)->unique()->values()->all();
    }

    public function removePetTag(int $petId): void
    {
        if ($this->petTaggingLocked) {
            $this->enforceFixedPetTag();

            return;
        }

        $this->selectedPetIds = collect($this->selectedPetIds)
            ->reject(fn (int $selectedPetId): bool => $selectedPetId === $petId)
            ->values()
            ->all();
    }

    public function isPetTagged(int $petId): bool
    {
        return in_array($petId, $this->selectedPetIds, true);
    }

    public function updatedLocationSearch(): void
    {
        $this->locationSearch = $this->normalizeNullableString($this->locationSearch);
        $this->locationDisplayText = null;
        $this->locationLat = null;
        $this->locationLng = null;

        if ($this->locationSearch === null || mb_strlen($this->locationSearch) < 2) {
            $this->locationSuggestions = [];
            $this->locationSuggestionsOpen = false;

            return;
        }

        $this->locationSuggestions = app(LocationAutocompleteService::class)
            ->suggest($this->locationSearch, (int) config('services.geocoding.limit', 5));
        $this->locationSuggestionsOpen = $this->locationSuggestions !== [];
    }

    public function selectLocationSuggestion(int $index): void
    {
        $suggestion = $this->locationSuggestions[$index] ?? null;

        if (! is_array($suggestion)) {
            return;
        }

        $this->storeLocationSuggestion($suggestion);
    }

    public function removeLocationTag(): void
    {
        $this->locationDisplayText = null;
        $this->locationSearch = null;
        $this->locationLat = null;
        $this->locationLng = null;
        $this->locationSuggestions = [];
        $this->locationSuggestionsOpen = false;
    }

    public function reverseGeocodeCoordinates(float|string $latitude, float|string $longitude): bool
    {
        if (! is_numeric($latitude) || ! is_numeric($longitude)) {
            return false;
        }

        $suggestion = app(LocationAutocompleteService::class)
            ->reverse((float) $latitude, (float) $longitude);

        if ($suggestion === null) {
            return false;
        }

        return $this->storeLocationSuggestion($suggestion);
    }

    public function selectMood(string $mood): void
    {
        $this->selectedMood = PostMood::normalize($mood);
    }

    public function removeMood(): void
    {
        $this->selectedMood = null;
    }

    public function queueLinkPreviewFetch(string $url, PostMetadataService $metadata): void
    {
        $detectedUrl = $metadata->extractFirstUrl($url);

        if (
            $detectedUrl === null
            || $this->linkPreviewData !== []
            || $this->dismissedLinkPreviewUrl === $detectedUrl
            || ! $metadata->isAllowedPreviewUrl($detectedUrl)
        ) {
            return;
        }

        $this->detectedLinkPreviewUrl = $detectedUrl;
        $this->linkPreviewRequestKey = (string) Str::uuid();
        $this->isLinkPreviewLoading = true;

        Cache::forget($this->linkPreviewCacheKey($this->linkPreviewRequestKey));

        FetchLinkPreviewMetadataJob::dispatch(
            url: $detectedUrl,
            cacheKey: $this->linkPreviewCacheKey($this->linkPreviewRequestKey),
        )->afterCommit();

        $this->dispatch('post-link-preview-queued', url: $detectedUrl);
    }

    public function pollLinkPreviewResult(): void
    {
        if (! $this->isLinkPreviewLoading || $this->linkPreviewRequestKey === null) {
            return;
        }

        $cacheKey = $this->linkPreviewCacheKey($this->linkPreviewRequestKey);
        $result = Cache::get($cacheKey);

        if (! is_array($result)) {
            return;
        }

        Cache::forget($cacheKey);
        $this->isLinkPreviewLoading = false;
        $this->linkPreviewRequestKey = null;

        if (($result['status'] ?? null) !== 'ready' || ! is_array($result['preview'] ?? null)) {
            $this->dispatch('post-link-preview-unavailable', url: $this->detectedLinkPreviewUrl);

            return;
        }

        $this->linkPreviewData = $result['preview'];
        $this->detectedLinkPreviewUrl = is_string($result['url'] ?? null)
            ? $result['url']
            : ($this->linkPreviewData['url'] ?? $this->detectedLinkPreviewUrl);
        $this->dismissedLinkPreviewUrl = null;

        $this->dispatch('post-link-preview-loaded', url: $this->detectedLinkPreviewUrl);
    }

    public function removeLinkPreview(): void
    {
        $currentUrl = $this->detectedLinkPreviewUrl;

        if ($currentUrl === null && is_string($this->linkPreviewData['url'] ?? null)) {
            $currentUrl = $this->linkPreviewData['url'];
        }

        $this->dismissedLinkPreviewUrl = $currentUrl;
        $this->linkPreviewData = [];
        $this->isLinkPreviewLoading = false;
        $this->linkPreviewRequestKey = null;
        $this->dispatch('post-link-preview-dismissed', url: $currentUrl);
    }

    public function setScheduledPost(
        string $utcDateTime,
        string $displayText,
        ?string $date = null,
        ?string $hour = null,
        ?string $minute = null,
    ): void {
        try {
            $scheduledAt = CarbonImmutable::parse($utcDateTime)->utc();
        } catch (Throwable) {
            $this->addError('scheduledPublishAt', 'Choose a valid future publish time.');

            return;
        }

        if ($scheduledAt->lessThanOrEqualTo(CarbonImmutable::now('UTC'))) {
            $this->addError('scheduledPublishAt', 'Choose a future publish time.');

            return;
        }

        $this->resetErrorBag('scheduledPublishAt');
        $this->scheduledPublishAt = $scheduledAt->toIso8601String();
        $this->scheduledDisplayText = trim(mb_substr($displayText, 0, 80));
        $this->scheduledDate = $this->normalizeNullableString($date);
        $this->scheduledHour = $this->normalizeNullableString($hour);
        $this->scheduledMinute = $this->normalizeNullableString($minute);
        $this->schedulePickerOpen = false;
    }

    public function clearSchedule(): void
    {
        $this->scheduledPublishAt = null;
        $this->scheduledDisplayText = null;
        $this->scheduledDate = null;
        $this->scheduledHour = null;
        $this->scheduledMinute = null;
        $this->schedulePickerOpen = false;
        $this->resetErrorBag('scheduledPublishAt');
    }

    public function selectVisibility(string $visibility): void
    {
        $normalizedVisibility = $this->normalizeVisibility($visibility);

        if ($normalizedVisibility === null) {
            return;
        }

        $this->selectedVisibility = $normalizedVisibility;
    }

    /**
     * @return list<array{value: string, label: string, description: string}>
     */
    public function visibilityOptions(): array
    {
        return [
            [
                'value' => Post::VISIBILITY_PUBLIC,
                'label' => 'Public',
                'description' => 'Anyone on PetSocial can see this post.',
            ],
            [
                'value' => Post::VISIBILITY_FOLLOWERS,
                'label' => 'Followers',
                'description' => 'People who follow you can see this post.',
            ],
            [
                'value' => Post::VISIBILITY_FRIENDS,
                'label' => 'Friends',
                'description' => 'Mutual followers can see this post.',
            ],
            [
                'value' => Post::VISIBILITY_PRIVATE,
                'label' => 'Only me',
                'description' => 'Only you can see this post.',
            ],
        ];
    }

    /**
     * @return array{value: string, label: string, description: string}
     */
    public function selectedVisibilityOption(): array
    {
        return collect($this->visibilityOptions())
            ->firstWhere('value', $this->selectedVisibility)
            ?? $this->visibilityOptions()[0];
    }

    public function petSpeciesLabel(Pet $pet): string
    {
        $species = $pet->getRelationValue('species');

        if ($species instanceof Species && filled($species->name)) {
            return (string) $species->name;
        }

        $legacySpecies = trim((string) $pet->getAttribute('species'));

        return $legacySpecies !== '' ? Str::headline(str_replace('_', ' ', $legacySpecies)) : 'Pet';
    }

    public function updatedMediaUploads(): void
    {
        $this->syncAttachmentMetadata();
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function registerUploadedAttachment(string $clientId, string $slot, string $temporaryPath, array $metadata = []): void
    {
        if (! in_array($slot, self::MEDIA_UPLOAD_SLOTS, true)) {
            return;
        }

        $file = $this->uploadedFileForSlot($slot);
        $mimeType = $this->normalizeNullableString($metadata['mime_type'] ?? $file?->getMimeType());
        $mediaType = str_starts_with((string) $mimeType, 'video/') ? 'video' : 'image';
        $existingIndex = $this->attachmentIndex($clientId);
        $existing = $existingIndex === null ? [] : $this->attachmentMetadata[$existingIndex];
        $attachment = [
            'client_id' => $clientId,
            'slot' => $slot,
            'temporary_path' => $this->temporaryPathForFile($file) ?? $temporaryPath,
            'preview_data_url' => null,
            'file_name' => $this->normalizeNullableString($metadata['file_name'] ?? $file?->getClientOriginalName()) ?? 'attachment',
            'media_type' => $mediaType,
            'mime_type' => $mimeType,
            'file_size' => (int) ($metadata['file_size'] ?? ($file?->getSize() ?? 0)),
            'alt_text' => $this->normalizeNullableString($existing['alt_text'] ?? $metadata['alt_text'] ?? null),
            'order' => (int) ($existing['order'] ?? $metadata['order'] ?? count($this->attachmentMetadata)),
        ];

        if ($existingIndex === null) {
            $this->attachmentMetadata[] = $attachment;
        } else {
            $this->attachmentMetadata[$existingIndex] = $attachment;
        }

        $this->normalizeAttachmentOrder();
        $this->refreshTemporaryFilePaths();
    }

    public function updateAttachmentAltText(int|string $identifier, string $altText): void
    {
        $index = $this->attachmentIndex($identifier);

        if ($index === null) {
            return;
        }

        $this->attachmentMetadata[$index]['alt_text'] = trim(mb_substr($altText, 0, 160)) ?: null;
    }

    /**
     * @param  list<string>  $clientIds
     */
    public function reorderAttachments(array $clientIds): void
    {
        $clientIds = collect($clientIds)
            ->filter(fn (mixed $clientId): bool => is_string($clientId) && $clientId !== '')
            ->values();

        if ($clientIds->isEmpty()) {
            return;
        }

        foreach ($clientIds as $order => $clientId) {
            $index = $this->attachmentIndex($clientId);

            if ($index !== null) {
                $this->attachmentMetadata[$index]['order'] = $order;
            }
        }

        $this->normalizeAttachmentOrder();
        $this->refreshTemporaryFilePaths();
    }

    public function removeAttachment(int|string $identifier): void
    {
        $index = $this->attachmentIndex($identifier);

        if ($index === null) {
            return;
        }

        $slot = $this->attachmentMetadata[$index]['slot'] ?? null;

        if (is_string($slot) && in_array($slot, self::MEDIA_UPLOAD_SLOTS, true)) {
            $this->{$slot} = null;
        }

        array_splice($this->attachmentMetadata, $index, 1);
        $this->normalizeAttachmentOrder();
        $this->refreshTemporaryFilePaths();
    }

    public function autosaveDraft(PostDraftService $drafts): void
    {
        $user = $this->viewer();

        if (! $user instanceof User || ! $this->hasDraftableContent()) {
            return;
        }

        $this->isAutoSavingDraft = true;

        try {
            $draft = $drafts->autosave($user, $this->draftPayload(), $this->contextType, $this->contextId);
            $this->draftId = $draft->exists ? (int) $draft->getKey() : null;
            $this->dispatch('post-draft-autosaved', draftId: $this->draftId);
        } finally {
            $this->isAutoSavingDraft = false;
        }
    }

    public function submit(CreatePostAction $posts, PostDraftService $drafts): void
    {
        $user = $this->viewer();

        abort_unless($user instanceof User, 403);

        $this->authorize('create', Post::class);
        $this->isSubmitting = true;
        $this->duplicateDetected = false;
        $this->duplicatePostId = null;

        try {
            $result = $posts->handle($user, $this->creationPayload());

            if ($result->duplicateDetected) {
                $this->duplicateDetected = true;
                $this->duplicatePostId = $result->duplicatePostId;
                $this->dispatch('post-duplicate-detected', postId: $result->duplicatePostId);

                return;
            }

            $post = $result->createdPost();
            $drafts->clear($user, $this->contextType, $this->contextId);
            $this->resetComposerState();

            if ($this->mode === self::MODE_MODAL) {
                $this->modalOpen = false;
            }

            $this->dispatch('post-composer-reset');
            $this->dispatch('post-created', postId: (int) $post->getKey(), mode: $this->mode);
            session()->flash('success', __('feed.flash_post_created'));
        } finally {
            $this->isSubmitting = false;
        }
    }

    public function confirmDuplicateAndSubmit(CreatePostAction $posts, PostDraftService $drafts): void
    {
        $this->confirmedDuplicate = true;
        $this->submit($posts, $drafts);
    }

    public function closeModal(): void
    {
        $this->modalOpen = false;
        $this->dispatch('post-composer-closed');
    }

    private function restoreDraft(): void
    {
        $user = $this->viewer();

        if (! $user instanceof User) {
            return;
        }

        $draft = app(PostDraftService::class)->restore($user, $this->contextType, $this->contextId);

        if (! $draft instanceof PostDraft) {
            return;
        }

        $this->draftId = (int) $draft->getKey();
        $this->textContent = (string) ($draft->body ?? '');
        $this->selectedVisibility = $this->normalizeVisibility($draft->visibility) ?? $this->selectedVisibility;
        $this->selectedMood = PostMood::normalize($draft->mood);
        $this->locationDisplayText = $draft->location;
        $this->locationSearch = $draft->location;
        $this->locationLat = $draft->location_lat === null ? null : (string) $draft->location_lat;
        $this->locationLng = $draft->location_lng === null ? null : (string) $draft->location_lng;
        if (! $this->petTaggingLocked) {
            $this->selectedPetIds = $this->normalizePetIds($draft->tagged_pets ?? []);
        }
        $this->linkPreviewData = is_array($draft->link_preview) ? $draft->link_preview : [];
        $this->detectedLinkPreviewUrl = is_string($this->linkPreviewData['url'] ?? null) ? $this->linkPreviewData['url'] : null;
        if ($draft->scheduled_publish_at !== null) {
            $scheduledAt = CarbonImmutable::instance($draft->scheduled_publish_at)->utc();
            $localScheduledAt = $scheduledAt->timezone((string) config('app.timezone'));

            $this->scheduledPublishAt = $scheduledAt->toIso8601String();
            $this->scheduledDisplayText = $localScheduledAt->format('M j, Y \a\t g:i A');
            $this->scheduledDate = $localScheduledAt->format('Y-m-d');
            $this->scheduledHour = $localScheduledAt->format('H');
            $this->scheduledMinute = $localScheduledAt->format('i');
        }
        $this->enforceFixedPetTag();
    }

    /**
     * @return array<string, mixed>
     */
    private function creationPayload(): array
    {
        return [
            'body' => $this->textContent,
            'pet_id' => $this->selectedPetIds[0] ?? null,
            'tagged_pets' => $this->selectedPetIds,
            'visibility' => $this->selectedVisibility,
            'status' => $this->scheduledPublishAt ? PostStatus::Scheduled->value : PostStatus::Published->value,
            'scheduled_publish_at' => $this->scheduledPublishAt,
            'mood' => $this->selectedMood,
            'location' => $this->locationDisplayText,
            'location_display_text' => $this->locationDisplayText,
            'location_lat' => $this->locationLat,
            'location_lng' => $this->locationLng,
            'link_preview' => $this->linkPreviewData ?: null,
            'link_preview_url' => $this->detectedLinkPreviewUrl,
            'media_attachments' => $this->mediaAttachmentPayload(),
            'confirmed_duplicate' => $this->confirmedDuplicate,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function draftPayload(): array
    {
        return [
            'body' => $this->textContent,
            'visibility' => $this->selectedVisibility,
            'mood' => $this->selectedMood,
            'location' => $this->locationDisplayText,
            'location_lat' => $this->locationLat,
            'location_lng' => $this->locationLng,
            'tagged_pets' => $this->selectedPetIds,
            'media_payload' => $this->attachmentMetadata,
            'link_preview' => $this->linkPreviewData,
            'scheduled_publish_at' => $this->scheduledPublishAt,
        ];
    }

    /**
     * @return list<array{temporary_path: string, media_type: string, alt_text: ?string, order: int, file_name: string, mime_type: ?string, file_size: int}>
     */
    private function mediaAttachmentPayload(): array
    {
        return collect($this->attachmentMetadata)
            ->filter(fn (array $attachment): bool => filled($attachment['temporary_path'] ?? null))
            ->sortBy(fn (array $attachment): int => (int) ($attachment['order'] ?? 0))
            ->map(fn (array $attachment): array => [
                'temporary_path' => (string) $attachment['temporary_path'],
                'media_type' => (string) ($attachment['media_type'] ?? 'image'),
                'alt_text' => filled($attachment['alt_text'] ?? null) ? (string) $attachment['alt_text'] : null,
                'order' => (int) ($attachment['order'] ?? 0),
                'file_name' => (string) ($attachment['file_name'] ?? 'attachment'),
                'mime_type' => filled($attachment['mime_type'] ?? null) ? (string) $attachment['mime_type'] : null,
                'file_size' => (int) ($attachment['file_size'] ?? 0),
            ])
            ->values()
            ->all();
    }

    private function syncAttachmentMetadata(): void
    {
        $attachments = collect($this->mediaUploads)
            ->filter(fn (mixed $file): bool => $file instanceof TemporaryUploadedFile)
            ->map(function (TemporaryUploadedFile $file, int $index): array {
                $temporaryPath = $file->getRealPath() ?: $file->getPathname();
                $mediaType = str_starts_with((string) $file->getMimeType(), 'video/') ? 'video' : 'image';

                return [
                    'client_id' => 'legacy-'.$index.'-'.md5($temporaryPath),
                    'slot' => null,
                    'temporary_path' => $temporaryPath,
                    'preview_data_url' => $this->temporaryPreviewUrl($file, $mediaType),
                    'file_name' => $file->getClientOriginalName(),
                    'media_type' => $mediaType,
                    'mime_type' => $file->getMimeType(),
                    'file_size' => (int) ($file->getSize() ?: 0),
                    'alt_text' => null,
                    'order' => $index,
                ];
            })
            ->take(self::MAX_ATTACHMENTS)
            ->values()
            ->all();

        $this->attachmentMetadata = $attachments;
        $this->refreshTemporaryFilePaths();
    }

    private function temporaryPreviewUrl(TemporaryUploadedFile $file, string $mediaType): ?string
    {
        if ($mediaType !== 'image') {
            return null;
        }

        try {
            return $file->temporaryUrl();
        } catch (\Throwable) {
            return null;
        }
    }

    private function uploadedFileForSlot(string $slot): ?TemporaryUploadedFile
    {
        if (! in_array($slot, self::MEDIA_UPLOAD_SLOTS, true)) {
            return null;
        }

        $file = $this->{$slot};

        return $file instanceof TemporaryUploadedFile ? $file : null;
    }

    private function temporaryPathForFile(?TemporaryUploadedFile $file): ?string
    {
        if (! $file instanceof TemporaryUploadedFile) {
            return null;
        }

        return $file->getRealPath() ?: $file->getPathname();
    }

    private function attachmentIndex(int|string $identifier): ?int
    {
        if (is_int($identifier) || ctype_digit((string) $identifier)) {
            $index = (int) $identifier;

            if (array_key_exists($index, $this->attachmentMetadata)) {
                return $index;
            }
        }

        foreach ($this->attachmentMetadata as $index => $attachment) {
            if (($attachment['client_id'] ?? null) === $identifier) {
                return $index;
            }
        }

        return null;
    }

    private function normalizeAttachmentOrder(): void
    {
        $this->attachmentMetadata = collect($this->attachmentMetadata)
            ->sortBy(fn (array $attachment): int => (int) ($attachment['order'] ?? 0))
            ->values()
            ->map(function (array $attachment, int $index): array {
                $attachment['order'] = $index;

                return $attachment;
            })
            ->all();
    }

    private function refreshTemporaryFilePaths(): void
    {
        $this->temporaryFilePaths = collect($this->attachmentMetadata)
            ->pluck('temporary_path')
            ->filter()
            ->values()
            ->all();
    }

    private function linkPreviewCacheKey(string $requestKey): string
    {
        $viewerId = (int) ($this->viewer()?->getKey() ?? 0);

        return "posts:link-preview:{$viewerId}:{$requestKey}";
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    /**
     * @param  list<int|string>  $petIds
     * @return list<int>
     */
    private function normalizePetIds(array $petIds): array
    {
        return collect($petIds)
            ->map(fn (mixed $petId): int => (int) $petId)
            ->filter(fn (int $petId): bool => $petId > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function normalizeVisibility(?string $visibility): ?string
    {
        $normalized = trim((string) $visibility);

        return in_array($normalized, Post::visibilityValues(), true) ? $normalized : null;
    }

    private function defaultVisibility(): string
    {
        $user = $this->viewer();

        return match ($user?->profile_visibility) {
            'private' => Post::VISIBILITY_PRIVATE,
            'followers_only' => Post::VISIBILITY_FOLLOWERS,
            default => Post::VISIBILITY_PUBLIC,
        };
    }

    private function hasDraftableContent(): bool
    {
        return trim($this->textContent) !== ''
            || $this->selectedPetIds !== []
            || $this->attachmentMetadata !== []
            || filled($this->locationDisplayText)
            || filled($this->selectedMood)
            || filled($this->scheduledPublishAt)
            || $this->linkPreviewData !== [];
    }

    private function resetComposerState(): void
    {
        $this->textContent = '';
        $this->mediaUploads = [];
        $this->temporaryFilePaths = [];
        $this->attachmentMetadata = [];
        $this->mediaErrors = [];
        foreach (self::MEDIA_UPLOAD_SLOTS as $slot) {
            $this->{$slot} = null;
        }
        $this->selectedPetIds = $this->fixedPetId !== null ? [$this->fixedPetId] : [];
        $this->locationDisplayText = null;
        $this->locationSearch = null;
        $this->locationLat = null;
        $this->locationLng = null;
        $this->locationSuggestions = [];
        $this->locationPickerOpen = false;
        $this->locationSuggestionsOpen = false;
        $this->selectedMood = null;
        $this->clearSchedule();
        $this->linkPreviewData = [];
        $this->detectedLinkPreviewUrl = null;
        $this->linkPreviewRequestKey = null;
        $this->dismissedLinkPreviewUrl = null;
        $this->isLinkPreviewLoading = false;
        $this->draftId = null;
        $this->duplicateDetected = false;
        $this->duplicatePostId = null;
        $this->confirmedDuplicate = false;
    }

    /**
     * @param  array{label?: mixed, latitude?: mixed, longitude?: mixed}  $suggestion
     */
    private function storeLocationSuggestion(array $suggestion): bool
    {
        if (! is_numeric($suggestion['latitude'] ?? null) || ! is_numeric($suggestion['longitude'] ?? null)) {
            return false;
        }

        $label = $this->normalizeNullableString($suggestion['label'] ?? null);

        if ($label === null) {
            return false;
        }

        $this->locationDisplayText = $label;
        $this->locationSearch = $label;
        $this->locationLat = (string) $suggestion['latitude'];
        $this->locationLng = (string) $suggestion['longitude'];
        $this->locationSuggestions = [];
        $this->locationSuggestionsOpen = false;

        return true;
    }

    private function enforceFixedPetTag(): void
    {
        if ($this->fixedPetId === null) {
            return;
        }

        $this->selectedPetIds = [$this->fixedPetId];
    }

    private function viewer(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }
};
?>

@php
 $composerId = 'post-composer-'.str_replace('.', '-', (string) $this->getId());
 $editorId = $composerId.'-editor';
 $titleId = $composerId.'-title';
 $isModal = $mode === 'modal';
 $surfaceClasses = $isModal ? 'w-full max-w-2xl rounded-[var(--radius-card)] bg-[color:var(--surface-modal)] shadow-card' : 'shell-card';
 $surfacePadding = $isModal ? 'p-5 sm:p-6' : 'p-6';
 $uploadSlots = [
     'mediaUploadSlot0',
     'mediaUploadSlot1',
     'mediaUploadSlot2',
     'mediaUploadSlot3',
     'mediaUploadSlot4',
     'mediaUploadSlot5',
     'mediaUploadSlot6',
     'mediaUploadSlot7',
     'mediaUploadSlot8',
     'mediaUploadSlot9',
 ];
 $availablePetsForTagging = $this->availablePets;
 $taggedPets = $availablePetsForTagging
     ->filter(fn (Pet $pet): bool => in_array((int) $pet->getKey(), $selectedPetIds, true))
     ->values();
 $visibilityOptions = $this->visibilityOptions();
 $selectedVisibilityOption = $this->selectedVisibilityOption();
 $moodOptions = PostMood::DISPLAY;
 $selectedMoodDisplay = $selectedMood ? ($moodOptions[$selectedMood] ?? null) : null;
 $linkPreviewUrl = is_string($linkPreviewData['url'] ?? null) ? (string) $linkPreviewData['url'] : null;
 $linkPreviewTitle = is_string($linkPreviewData['title'] ?? null) ? (string) $linkPreviewData['title'] : null;
 $linkPreviewDescription = is_string($linkPreviewData['description'] ?? null) ? (string) $linkPreviewData['description'] : null;
 $linkPreviewImage = is_string($linkPreviewData['image'] ?? null) ? (string) $linkPreviewData['image'] : null;
 $linkPreviewDomain = is_string($linkPreviewData['domain'] ?? null)
     ? (string) $linkPreviewData['domain']
     : ($linkPreviewUrl ? parse_url($linkPreviewUrl, PHP_URL_HOST) : null);
 $minuteOptions = ['00', '15', '30', '45'];
 $visibilityIcon = static function (string $visibility, string $classes = 'h-4 w-4'): string {
     $baseAttributes = 'class="'.$classes.'" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"';

     return match ($visibility) {
         Post::VISIBILITY_FOLLOWERS => '<svg '.$baseAttributes.'><path d="M7.5 9a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"/><path d="M2.5 17c.5-3 2.3-5 5-5s4.5 2 5 5"/><path d="M14 9.5a2.5 2.5 0 0 0 0-5"/><path d="M13.5 12.5c2 .4 3.3 2 3.7 4.5"/></svg>',
         Post::VISIBILITY_FRIENDS => '<svg '.$baseAttributes.'><path d="M7 9a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"/><path d="M2 17c.5-3 2.3-5 5-5s4.5 2 5 5"/><path d="M13 9a3 3 0 1 0 0-6"/><path d="M12.5 12c2.7 0 4.5 2 5 5"/></svg>',
         Post::VISIBILITY_PRIVATE => '<svg '.$baseAttributes.'><path d="M5.5 8.5h9v7h-9z"/><path d="M7.5 8.5V6a2.5 2.5 0 0 1 5 0v2.5"/><path d="M10 11.5v1.5"/></svg>',
         default => '<svg '.$baseAttributes.'><circle cx="10" cy="10" r="7"/><path d="M3.5 10h13"/><path d="M10 3c2 2 3 4.3 3 7s-1 5-3 7"/><path d="M10 3c-2 2-3 4.3-3 7s1 5 3 7"/></svg>',
     };
 };
@endphp

<div
 x-data="postComposer({
 text: @js($textContent),
 maxCharacters: 1000,
 maxAttachments: 10,
 uploadSlots: @js($uploadSlots),
 })"
 x-on:post-composer-reset.window="resetLocalAttachments"
>
 @if ($isModal)
 <div
 x-show="$wire.modalOpen"
 x-cloak
 class="fixed inset-0 z-50 flex items-end justify-center bg-bark/45 p-0 sm:items-center sm:p-6"
 role="dialog"
 aria-modal="true"
 aria-labelledby="{{ $titleId }}"
 x-on:keydown.escape.window="$wire.closeModal()"
 >
 <button type="button" class="absolute inset-0 cursor-default" aria-label="Close post composer" wire:click="closeModal"></button>
 <div class="{{ $surfaceClasses }} relative max-h-[92vh] overflow-y-auto">
 @else
 <section class="{{ $surfaceClasses }}">
 @endif
 <div class="{{ $surfacePadding }}">
 <form
 wire:submit="submit"
 class="relative space-y-5 rounded-[var(--radius-card)] border border-transparent transition"
 x-bind:class="{ 'border-dashed border-paw bg-paw/5 ring-2 ring-paw/15': isDragging }"
 x-on:dragover.prevent="handleDragOver"
 x-on:dragleave="handleDragLeave"
 x-on:drop.prevent="handleDrop"
 >
 <div
 x-cloak
 x-show="isDragging"
 x-transition.opacity
 class="pointer-events-none absolute inset-0 z-20 flex items-center justify-center rounded-[var(--radius-card)] bg-warm-white/75 backdrop-blur-[1px]"
 aria-hidden="true"
 >
 <div class="inline-flex items-center gap-2 rounded-full border border-paw/30 bg-warm-white px-4 py-2 text-sm font-semibold text-paw shadow-sm">
 <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
 <path d="M10 3v10"/>
 <path d="m6 9 4 4 4-4"/>
 <path d="M4 17h12"/>
 </svg>
 Drop to attach
 </div>
 </div>
 <div class="flex items-start justify-between gap-4">
 <div class="min-w-0">
 <p id="{{ $titleId }}" class="font-display text-lg font-bold text-bark">Create a post</p>
 <p class="mt-1 text-sm leading-6 text-fur">Share a pet moment, care question, or update.</p>
 </div>

 @if ($isModal)
 <button
 type="button"
 class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-[var(--radius-soft)] text-fur transition hover:bg-cream hover:text-bark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw"
 wire:click="closeModal"
 aria-label="Close post composer"
 >
 <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
 <path d="M5 5l10 10M15 5 5 15"/>
 </svg>
 </button>
 @endif
 </div>

 @if ($duplicateDetected)
 <div class="rounded-[var(--radius-soft)] border border-amber/30 bg-amber-light/40 p-4" role="alert">
 <p class="text-sm font-semibold text-bark">This looks like something you posted recently.</p>
 <div class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-center">
 <x-ui.button type="button" variant="secondary" size="sm" wire:click="confirmDuplicateAndSubmit" wire:loading.attr="disabled" wire:target="confirmDuplicateAndSubmit">
 Post anyway
 </x-ui.button>
 <button type="button" class="text-sm font-semibold text-fur hover:text-bark" wire:click="$set('duplicateDetected', false)">Keep editing</button>
 </div>
 </div>
 @endif

 <div class="space-y-2">
 <label for="{{ $editorId }}" class="text-sm font-semibold text-bark">What would you like to share?</label>
 <div
 id="{{ $editorId }}"
 x-ref="editor"
 wire:ignore
 role="textbox"
 aria-multiline="true"
 contenteditable="true"
 data-placeholder="Share a walk, a tiny victory, a question, or a moment worth remembering."
 class="min-h-32 w-full rounded-[var(--radius-soft)] border border-whisker/40 bg-[color:var(--surface-form)] px-4 py-3 text-base leading-7 text-bark outline-none transition empty:before:content-[attr(data-placeholder)] empty:before:text-whisker focus:border-paw focus:ring-2 focus:ring-paw/15"
 x-on:input.debounce.150ms="syncFromEditor"
 x-on:paste="handlePasteForLinkPreview($event)"
>{{ $textContent }}</div>

 <div class="flex min-h-8 flex-wrap items-center justify-between gap-3">
 @error('body')
 <p class="text-sm font-medium text-rose">{{ $message }}</p>
 @else
 <p class="text-xs text-fur">Hashtags and mentions are highlighted while you type.</p>
 @enderror

 <span class="sr-only" aria-live="polite">Current character count: {{ $this->characterCount }}</span>

 <div
 x-cloak
 x-show="showCharacterCounter"
 class="flex items-center gap-2 text-xs font-semibold"
 :class="{ 'text-amber': !isCounterDanger, 'text-rose': isCounterDanger }"
 aria-live="polite"
 >
 <svg class="h-7 w-7 -rotate-90" viewBox="0 0 28 28" aria-hidden="true">
 <circle cx="14" cy="14" r="12" fill="none" class="stroke-whisker/30" stroke-width="3"></circle>
 <circle
 cx="14"
 cy="14"
 r="12"
 fill="none"
 class="stroke-current transition-[stroke-dashoffset] duration-150"
 stroke-width="3"
 stroke-linecap="round"
 :stroke-dasharray="circumference"
 :stroke-dashoffset="progressOffset"
 ></circle>
 </svg>
 <span x-show="overLimitCount === 0"><span x-text="characterCount"></span>/1000</span>
 <span x-show="overLimitCount > 0">Delete <span x-text="overLimitCount"></span></span>
 </div>
 </div>
 </div>

 @if ($selectedMoodDisplay !== null)
 <p class="inline-flex max-w-full items-center gap-1.5 text-sm italic text-fur" aria-live="polite">
 <span class="truncate">feeling {{ $selectedMoodDisplay['emoji'] }} {{ Str::lower($selectedMoodDisplay['label']) }}</span>
 <button
 type="button"
 wire:click="removeMood"
 class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-fur transition hover:bg-cream hover:text-rose focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw"
 aria-label="Remove mood"
 >
 <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
 <path d="M5 5l10 10M15 5 5 15"/>
 </svg>
 </button>
 </p>
 @endif

 @if (filled($scheduledPublishAt) && filled($scheduledDisplayText))
 <p class="inline-flex max-w-full items-center gap-1.5 text-sm font-semibold text-amber-dark" aria-live="polite">
 <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
 <path d="M5 3v3"/>
 <path d="M15 3v3"/>
 <path d="M3.5 7.5h13"/>
 <path d="M5.5 4.5h9A2.5 2.5 0 0 1 17 7v8a2.5 2.5 0 0 1-2.5 2.5h-9A2.5 2.5 0 0 1 3 15V7a2.5 2.5 0 0 1 2.5-2.5Z"/>
 </svg>
 <span class="truncate">Scheduled for {{ $scheduledDisplayText }}</span>
 <button
 type="button"
 wire:click="clearSchedule"
 class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-amber-dark transition hover:bg-amber/15 hover:text-rose focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw"
 aria-label="Cancel scheduled post"
 >
 <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
 <path d="M5 5l10 10M15 5 5 15"/>
 </svg>
 </button>
 </p>
 @endif

 @if ($taggedPets->isNotEmpty() || filled($locationDisplayText))
 <div class="flex flex-wrap gap-2" aria-label="Post tags">
 @if (filled($locationDisplayText))
 <span class="inline-flex max-w-full items-center gap-1.5 rounded-full border border-leaf/15 bg-leaf/10 py-1 ps-2 pe-1 text-xs font-semibold text-leaf-dark">
 <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
 <path d="M10 18s6-5.4 6-10A6 6 0 1 0 4 8c0 4.6 6 10 6 10Z"/>
 <circle cx="10" cy="8" r="2"/>
 </svg>
 <span class="truncate">{{ $locationDisplayText }}</span>
 <button
 type="button"
 wire:click="removeLocationTag"
 class="inline-flex h-5 w-5 items-center justify-center rounded-full text-leaf transition hover:bg-leaf/15 hover:text-rose focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw"
 aria-label="Remove location tag"
 >
 <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
 <path d="M5 5l10 10M15 5 5 15"/>
 </svg>
 </button>
 </span>
 @endif

 @foreach ($taggedPets as $taggedPet)
 <span class="inline-flex max-w-full items-center gap-1.5 rounded-full border border-paw/15 bg-paw/10 py-1 ps-1 pe-2 text-xs font-semibold text-paw-dark">
 <img src="{{ $taggedPet->avatar_url }}" alt="" class="h-5 w-5 rounded-full border border-warm-white object-cover" loading="lazy">
 <span class="truncate">{{ $taggedPet->name }}</span>
 @unless ($petTaggingLocked)
 <button
 type="button"
 wire:click="removePetTag({{ (int) $taggedPet->getKey() }})"
 class="inline-flex h-5 w-5 items-center justify-center rounded-full text-paw transition hover:bg-paw/15 hover:text-rose focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw"
 aria-label="Remove {{ $taggedPet->name }} tag"
 >
 <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
 <path d="M5 5l10 10M15 5 5 15"/>
 </svg>
 </button>
 @endunless
 </span>
 @endforeach
 </div>
 @endif

 @if ($schedulePickerOpen)
 <div
 class="rounded-[var(--radius-soft)] border border-amber/25 bg-amber-light/25 p-4"
 x-data="postSchedulePicker({
 initialIso: @js($scheduledPublishAt),
 selectedDate: @js($scheduledDate),
 selectedHour: @js($scheduledHour),
 selectedMinute: @js($scheduledMinute),
 })"
 x-init="init()"
 wire:transition
 >
 <div class="flex flex-col gap-4 lg:flex-row">
 <div class="min-w-0 flex-1">
 <div class="mb-3 flex items-center justify-between gap-3">
 <button
 type="button"
 class="inline-flex h-9 w-9 items-center justify-center rounded-full text-fur transition hover:bg-warm-white hover:text-bark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw"
 x-on:click="previousMonth()"
 aria-label="Previous month"
 >
 <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
 <path d="m12 15-5-5 5-5"/>
 </svg>
 </button>
 <p class="text-sm font-bold text-bark" x-text="monthLabel"></p>
 <button
 type="button"
 class="inline-flex h-9 w-9 items-center justify-center rounded-full text-fur transition hover:bg-warm-white hover:text-bark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw"
 x-on:click="nextMonth()"
 aria-label="Next month"
 >
 <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
 <path d="m8 5 5 5-5 5"/>
 </svg>
 </button>
 </div>

 <div class="grid grid-cols-7 gap-1 text-center text-[0.7rem] font-bold uppercase tracking-wide text-fur" aria-hidden="true">
 <span>Sun</span>
 <span>Mon</span>
 <span>Tue</span>
 <span>Wed</span>
 <span>Thu</span>
 <span>Fri</span>
 <span>Sat</span>
 </div>

 <div class="mt-2 grid grid-cols-7 gap-1" role="grid" aria-label="Select a date">
 <template x-for="day in calendarDays" :key="day.key">
 <button
 type="button"
 class="aspect-square rounded-full text-sm font-semibold transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw disabled:cursor-not-allowed disabled:opacity-35"
 x-bind:class="dayButtonClass(day)"
 x-bind:disabled="day.disabled || !day.inMonth"
 x-on:click="selectDate(day.iso)"
 x-bind:aria-pressed="(selectedDate === day.iso).toString()"
 x-text="day.day"
 ></button>
 </template>
 </div>
 </div>

 <div class="w-full space-y-4 lg:w-56">
 <div>
 <p class="text-sm font-bold text-bark">Select a time</p>
 <p class="mt-1 text-xs leading-5 text-fur">Times use your local timezone and publish in 15-minute increments.</p>
 </div>
 <div class="grid grid-cols-2 gap-2">
 <label class="space-y-1">
 <span class="text-xs font-semibold text-fur">Hour</span>
 <select
 x-model="selectedHour"
 class="h-[var(--control-height-md)] w-full rounded-[var(--radius-soft)] border border-whisker/40 bg-[color:var(--surface-form)] px-3 text-sm font-semibold text-bark focus:border-paw focus:outline-none focus:ring-2 focus:ring-paw/15"
 aria-label="Scheduled hour"
 >
 @for ($hour = 0; $hour < 24; $hour++)
 @php
     $hourValue = str_pad((string) $hour, 2, '0', STR_PAD_LEFT);
 @endphp
 <option value="{{ $hourValue }}" x-bind:disabled="isTimeDisabled('{{ $hourValue }}', selectedMinute)">{{ $hourValue }}</option>
 @endfor
 </select>
 </label>

 <label class="space-y-1">
 <span class="text-xs font-semibold text-fur">Minute</span>
 <select
 x-model="selectedMinute"
 class="h-[var(--control-height-md)] w-full rounded-[var(--radius-soft)] border border-whisker/40 bg-[color:var(--surface-form)] px-3 text-sm font-semibold text-bark focus:border-paw focus:outline-none focus:ring-2 focus:ring-paw/15"
 aria-label="Scheduled minute"
 >
 @foreach ($minuteOptions as $minuteValue)
 <option value="{{ $minuteValue }}" x-bind:disabled="isTimeDisabled(selectedHour, '{{ $minuteValue }}')">{{ $minuteValue }}</option>
 @endforeach
 </select>
 </label>
 </div>

 <p class="text-sm font-semibold text-amber-dark" x-show="previewText" x-text="`Scheduled for ${previewText}`"></p>
 <button
 type="button"
 class="inline-flex h-[var(--control-height-md)] w-full items-center justify-center gap-2 rounded-[var(--radius-soft)] bg-amber px-4 text-sm font-bold text-bark transition hover:bg-amber-dark hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw disabled:cursor-not-allowed disabled:opacity-50"
 x-bind:disabled="!canApply"
 x-on:click="applySchedule($wire)"
 >
 Apply schedule
 </button>
 </div>
 </div>

 @error('scheduledPublishAt')
 <p class="mt-3 text-sm font-medium text-rose">{{ $message }}</p>
 @enderror
 </div>
 @endif

 @if ($locationPickerOpen)
 <div class="rounded-[var(--radius-soft)] border border-whisker/30 bg-cream/40 p-3" wire:transition>
 <div class="relative">
 <label for="{{ $composerId }}-location-search" class="sr-only">Add a location</label>
 <div class="flex gap-2">
 <input
 id="{{ $composerId }}-location-search"
 type="text"
 class="h-[var(--control-height-md)] min-w-0 flex-1 rounded-[var(--radius-soft)] border border-whisker/40 bg-[color:var(--surface-form)] px-3 text-sm text-bark placeholder:text-whisker transition focus:border-paw focus:outline-none focus:ring-2 focus:ring-paw/15"
 placeholder="Add a location."
 autocomplete="off"
 wire:model.live.debounce.400ms="locationSearch"
 aria-autocomplete="list"
 aria-expanded="{{ $locationSuggestionsOpen ? 'true' : 'false' }}"
 aria-controls="{{ $composerId }}-location-suggestions"
 >
 <button
 type="button"
 class="inline-flex h-[var(--control-height-md)] w-[var(--control-height-md)] shrink-0 items-center justify-center rounded-[var(--radius-soft)] border border-whisker/40 bg-[color:var(--surface-form)] text-fur transition hover:border-leaf hover:bg-leaf/10 hover:text-leaf focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw disabled:cursor-not-allowed disabled:opacity-50"
 x-on:click="useCurrentLocation()"
 x-bind:disabled="reverseGeocoding || !geolocationAvailable"
 x-bind:aria-busy="reverseGeocoding.toString()"
 aria-label="Use current location"
 >
 <svg x-show="!reverseGeocoding" class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
 <circle cx="10" cy="10" r="6"/>
 <path d="M10 2v2"/>
 <path d="M10 16v2"/>
 <path d="M2 10h2"/>
 <path d="M16 10h2"/>
 <circle cx="10" cy="10" r="1.5"/>
 </svg>
 <svg x-cloak x-show="reverseGeocoding" class="h-5 w-5 animate-spin" viewBox="0 0 20 20" fill="none" aria-hidden="true">
 <circle class="stroke-current opacity-25" cx="10" cy="10" r="7" stroke-width="2"></circle>
 <path class="fill-current" d="M17 10a7 7 0 0 0-7-7V1a9 9 0 0 1 9 9h-2Z"></path>
 </svg>
 </button>
 </div>

 <div class="mt-1 min-h-5 text-xs" aria-live="polite">
 <span wire:loading wire:target="locationSearch" class="text-fur">Searching locations...</span>
 <span x-cloak x-show="locationError" x-text="locationError" class="font-medium text-rose"></span>
 </div>

 @if ($locationSuggestionsOpen && $locationSuggestions !== [])
 <ul
 id="{{ $composerId }}-location-suggestions"
 class="absolute left-0 top-full z-30 mt-1 max-h-56 w-full overflow-y-auto rounded-[var(--radius-card)] border border-whisker/30 bg-warm-white p-1 shadow-card"
 role="listbox"
 aria-label="Location suggestions"
 >
 @foreach ($locationSuggestions as $index => $suggestion)
 @php
     $suggestionLabel = (string) ($suggestion['label'] ?? '');
     $suggestionName = trim((string) ($suggestion['name'] ?? Str::before($suggestionLabel, ',')));
     $suggestionRegion = trim((string) ($suggestion['region'] ?? (str_contains($suggestionLabel, ',') ? Str::of($suggestionLabel)->after(',')->squish()->toString() : '')));
 @endphp
 <li wire:key="post-location-suggestion-{{ $index }}" role="option">
 <button
 type="button"
 class="flex w-full flex-col rounded-[var(--radius-soft)] px-3 py-2 text-left transition hover:bg-cream focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw"
 wire:click="selectLocationSuggestion({{ $index }})"
 >
 <span class="text-sm font-semibold text-bark">{{ $suggestionName !== '' ? $suggestionName : $suggestionLabel }}</span>
 @if ($suggestionRegion !== '')
 <span class="text-xs text-fur">{{ $suggestionRegion }}</span>
 @endif
 </button>
 </li>
 @endforeach
 </ul>
 @endif
 </div>
 </div>
 @endif

 @if ($isLinkPreviewLoading)
 <div
 wire:poll.2s="pollLinkPreviewResult"
 class="overflow-hidden rounded-[var(--radius-soft)] border border-whisker/30 bg-warm-white"
 aria-live="polite"
 aria-label="Loading link preview"
 >
 <div class="h-32 max-h-[200px] animate-pulse bg-cream"></div>
 <div class="space-y-2 p-4">
 <div class="h-3 w-24 animate-pulse rounded-full bg-whisker/30"></div>
 <div class="h-4 w-3/4 animate-pulse rounded-full bg-whisker/40"></div>
 <div class="h-3 w-full animate-pulse rounded-full bg-whisker/25"></div>
 <div class="h-3 w-2/3 animate-pulse rounded-full bg-whisker/25"></div>
 </div>
 </div>
 @elseif ($linkPreviewUrl && $linkPreviewTitle)
 <div class="relative overflow-hidden rounded-[var(--radius-soft)] border border-whisker/30 bg-warm-white">
 <button
 type="button"
 wire:click="removeLinkPreview"
 class="absolute right-2 top-2 z-10 inline-flex h-8 w-8 items-center justify-center rounded-full bg-bark/75 text-white transition hover:bg-rose focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw"
 aria-label="Dismiss link preview"
 >
 <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
 <path d="M5 5l10 10M15 5 5 15"/>
 </svg>
 </button>

 @if ($linkPreviewImage)
 <img src="{{ $linkPreviewImage }}" alt="" class="max-h-[200px] w-full object-cover" loading="lazy">
 @endif

 <div class="p-4">
 @if ($linkPreviewDomain)
 <p class="text-xs font-semibold uppercase tracking-[0.12em] text-fur">{{ $linkPreviewDomain }}</p>
 @endif
 <p class="mt-1 line-clamp-2 text-sm font-bold text-bark">{{ $linkPreviewTitle }}</p>
 @if ($linkPreviewDescription)
 <p class="mt-2 line-clamp-2 text-sm leading-6 text-fur">{{ $linkPreviewDescription }}</p>
 @endif
 </div>
 </div>
 @endif

 <div class="space-y-3">
 <div class="flex flex-wrap items-center justify-between gap-3 border-y border-whisker/25 py-3">
 <div class="flex items-center gap-2">
 <button
 type="button"
 class="inline-flex h-10 w-10 items-center justify-center rounded-full text-fur transition hover:bg-paw/10 hover:text-paw focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw"
 x-on:click="$refs.mediaInput.click()"
 aria-label="Attach photo or video"
 >
 <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
 <path d="M4 5.5A2.5 2.5 0 0 1 6.5 3h7A2.5 2.5 0 0 1 16 5.5v9A2.5 2.5 0 0 1 13.5 17h-7A2.5 2.5 0 0 1 4 14.5z"/>
 <path d="m5 14 3.5-3.5 2.5 2.5 1.5-1.5L16 15"/>
 <circle cx="13" cy="7" r="1.25"/>
 </svg>
 </button>
 <div class="relative" x-data="{ open: false }" x-on:keydown.escape.window="open = false">
 <button
 type="button"
 class="{{ $selectedMoodDisplay !== null ? 'bg-amber/10 text-amber-dark' : 'text-fur hover:bg-amber/10 hover:text-amber-dark' }} inline-flex h-10 w-10 items-center justify-center rounded-full transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw"
 x-on:click="open = !open"
 aria-label="Add mood"
 aria-haspopup="true"
 x-bind:aria-expanded="open.toString()"
 >
 @if ($selectedMoodDisplay !== null)
 <span class="text-lg leading-none" aria-hidden="true">{{ $selectedMoodDisplay['emoji'] }}</span>
 @else
 <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
 <circle cx="10" cy="10" r="7"/>
 <path d="M7 8h.01"/>
 <path d="M13 8h.01"/>
 <path d="M7.5 12c.7 1 1.5 1.5 2.5 1.5s1.8-.5 2.5-1.5"/>
 </svg>
 @endif
 </button>

 <div
 x-cloak
 x-show="open"
 x-transition.origin.top.left
 x-on:click.outside="open = false"
 class="absolute left-0 top-full z-30 mt-2 w-72 max-w-[calc(100vw-2rem)] overflow-hidden rounded-[var(--radius-card)] border border-whisker/30 bg-warm-white shadow-card"
 role="listbox"
 aria-label="Mood"
 >
 <div class="border-b border-whisker/20 px-4 py-3">
 <p class="text-sm font-semibold text-bark">Mood</p>
 </div>

 <div class="grid grid-cols-3 gap-2 p-2">
 @foreach ($moodOptions as $moodValue => $moodDisplay)
 @php
     $isSelectedMood = $selectedMood === $moodValue;
 @endphp
 <button
 type="button"
 wire:click="selectMood('{{ $moodValue }}')"
 x-on:click="open = false"
 class="{{ $isSelectedMood ? 'border-amber bg-amber/10 text-bark ring-2 ring-amber/15' : 'border-whisker/30 text-fur hover:border-amber/50 hover:bg-cream hover:text-bark' }} flex min-h-20 flex-col items-center justify-center gap-1 rounded-[var(--radius-soft)] border px-2 py-2 text-center transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw"
 aria-pressed="{{ $isSelectedMood ? 'true' : 'false' }}"
 role="option"
 aria-selected="{{ $isSelectedMood ? 'true' : 'false' }}"
 >
 <span class="text-2xl leading-none" aria-hidden="true">{{ $moodDisplay['emoji'] }}</span>
 <span class="text-xs font-semibold">{{ $moodDisplay['label'] }}</span>
 </button>
 @endforeach
 </div>
 </div>
 </div>
 <button
 type="button"
 class="{{ $schedulePickerOpen || filled($scheduledPublishAt) ? 'bg-amber/10 text-amber-dark' : 'text-fur hover:bg-amber/10 hover:text-amber-dark' }} inline-flex h-10 w-10 items-center justify-center rounded-full transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw"
 wire:click="$toggle('schedulePickerOpen')"
 aria-label="Schedule post"
 aria-expanded="{{ $schedulePickerOpen ? 'true' : 'false' }}"
 >
 <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
 <circle cx="10" cy="10" r="7"/>
 <path d="M10 6v4l2.5 1.5"/>
 </svg>
 </button>
 <button
 type="button"
 class="{{ $locationPickerOpen || filled($locationDisplayText) ? 'bg-leaf/10 text-leaf' : 'text-fur hover:bg-leaf/10 hover:text-leaf' }} inline-flex h-10 w-10 items-center justify-center rounded-full transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw"
 wire:click="$toggle('locationPickerOpen')"
 aria-label="Add location"
 aria-expanded="{{ $locationPickerOpen ? 'true' : 'false' }}"
 >
 <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
 <path d="M10 18s6-5.4 6-10A6 6 0 1 0 4 8c0 4.6 6 10 6 10Z"/>
 <circle cx="10" cy="8" r="2"/>
 </svg>
 </button>
 @unless ($petTaggingLocked)
 <div class="relative" x-data="{ open: false }" x-on:keydown.escape.window="open = false">
 <button
 type="button"
 class="inline-flex h-10 w-10 items-center justify-center rounded-full text-fur transition hover:bg-paw/10 hover:text-paw focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw"
 x-on:click="open = !open"
 aria-label="Tag a pet"
 aria-haspopup="listbox"
 x-bind:aria-expanded="open.toString()"
 >
 <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
 <path d="M8.3 5.2c.4.9.1 1.9-.6 2.2s-1.6-.2-2-1.1-.1-1.9.6-2.2 1.6.2 2 1.1Z"/>
 <path d="M14.3 6.3c-.4.9-1.3 1.4-2 1.1s-1-1.3-.6-2.2 1.3-1.4 2-1.1 1 1.3.6 2.2Z"/>
 <path d="M5.4 10.6c.9.4 1.4 1.3 1.1 2s-1.3 1-2.2.6-1.4-1.3-1.1-2 1.3-1 2.2-.6Z"/>
 <path d="M15.7 13.2c-.9.4-1.9.1-2.2-.6s.2-1.6 1.1-2 1.9-.1 2.2.6-.2 1.6-1.1 2Z"/>
 <path d="M7.2 14.8c.4-1.9 1.6-3.3 2.8-3.3s2.4 1.4 2.8 3.3c.2 1-.4 1.9-1.4 1.9H8.6c-1 0-1.6-.9-1.4-1.9Z"/>
 </svg>
 </button>

 <div
 x-cloak
 x-show="open"
 x-transition.origin.top.left
 x-on:click.outside="open = false"
 class="absolute left-0 top-full z-30 mt-2 w-80 max-w-[calc(100vw-2rem)] overflow-hidden rounded-[var(--radius-card)] border border-whisker/30 bg-warm-white shadow-card"
 role="listbox"
 aria-label="Tag pets"
 >
 <div class="border-b border-whisker/20 px-4 py-3">
 <p class="text-sm font-semibold text-bark">Tag a pet</p>
 <p class="mt-0.5 text-xs text-fur">Choose one or more pets for this post.</p>
 </div>

 <div class="max-h-72 overflow-y-auto p-2">
 @forelse ($availablePetsForTagging as $pet)
 @php
     $petId = (int) $pet->getKey();
     $isTagged = $this->isPetTagged($petId);
 @endphp
 <button
 type="button"
 wire:click="togglePetTag({{ $petId }})"
 class="flex w-full items-center gap-3 rounded-[var(--radius-soft)] px-3 py-2 text-left transition hover:bg-cream focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw"
 aria-pressed="{{ $isTagged ? 'true' : 'false' }}"
 role="option"
 aria-selected="{{ $isTagged ? 'true' : 'false' }}"
 >
 <img src="{{ $pet->avatar_url }}" alt="" class="h-8 w-8 rounded-full border border-whisker/30 object-cover" loading="lazy">
 <span class="min-w-0 flex-1">
 <span class="flex min-w-0 items-center gap-2">
 <span class="truncate text-sm font-bold text-bark">{{ $pet->name }}</span>
 <span class="inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full border {{ $isTagged ? 'border-paw bg-paw text-white' : 'border-whisker/50 text-transparent' }}" aria-hidden="true">
 <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
 <path d="m5 10 3 3 7-7"/>
 </svg>
 </span>
 </span>
 <span class="mt-1 inline-flex">
 <x-ui.badge variant="primary" size="sm">{{ $this->petSpeciesLabel($pet) }}</x-ui.badge>
 </span>
 </span>
 </button>
 @empty
 <p class="px-3 py-6 text-center text-sm text-fur">No pets available to tag yet.</p>
 @endforelse
 </div>
 </div>
 </div>
 @endunless
 <input
 x-ref="mediaInput"
 type="file"
 multiple
 accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/quicktime"
 class="sr-only"
 x-on:change="handleFileSelection($event.target.files); $event.target.value = ''"
 >
 <p class="text-xs leading-5 text-fur">Up to 10 images or videos. Images 10 MB, videos 100 MB.</p>
 </div>

 <p class="text-xs font-semibold text-fur" aria-live="polite">
 <span x-text="attachments.length"></span>/10 attachments
 </p>
 </div>

 <div
 x-cloak
 x-show="mediaErrors.length > 0"
 class="space-y-1 rounded-[var(--radius-soft)] border border-rose/25 bg-rose/5 p-3"
 role="alert"
 >
 <template x-for="error in mediaErrors" :key="error">
 <p class="text-sm font-medium text-rose" x-text="error"></p>
 </template>
 </div>

 @error('media_attachments')
 <p class="text-sm font-medium text-rose">{{ $message }}</p>
 @enderror
 @error('media')
 <p class="text-sm font-medium text-rose">{{ $message }}</p>
 @enderror

 <div x-cloak x-show="attachments.length > 0" x-transition class="min-w-0">
 <ul x-ref="attachmentStrip" class="flex gap-3 overflow-x-auto pb-2" aria-label="Selected attachments">
 <template x-for="attachment in attachments" :key="attachment.client_id">
 <li
 draggable="true"
 class="w-36 shrink-0 cursor-grab rounded-[var(--radius-soft)] border border-whisker/30 bg-warm-white p-2 transition active:cursor-grabbing"
 x-bind:data-client-id="attachment.client_id"
 x-bind:class="{ 'scale-95 opacity-0': attachment.removing }"
 x-on:dragstart="startAttachmentDrag(attachment.client_id)"
 x-on:dragover.prevent
 x-on:drop.prevent="dropAttachmentOn(attachment.client_id)"
 x-on:dragend="draggingAttachmentId = null"
 >
 <div class="relative aspect-square overflow-hidden rounded-[var(--radius-soft)] bg-cream">
 <template x-if="attachment.media_type === 'image'">
 <img x-bind:src="attachment.preview_data_url" alt="" class="h-full w-full object-cover">
 </template>
 <template x-if="attachment.media_type === 'video'">
 <video x-bind:src="attachment.preview_data_url" class="h-full w-full object-cover" muted playsinline preload="metadata"></video>
 </template>

 <button
 type="button"
 class="absolute right-1 top-1 inline-flex h-7 w-7 items-center justify-center rounded-full bg-bark/75 text-white transition hover:bg-rose focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw"
 x-on:click="removeAttachment(attachment.client_id)"
 x-bind:aria-label="`Remove ${attachment.file_name}`"
 >
 <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
 <path d="M5 5l10 10M15 5 5 15"/>
 </svg>
 </button>

 <div
 x-show="attachment.upload_state === 'uploading'"
 class="absolute inset-0 flex items-center justify-center bg-bark/45"
 aria-hidden="true"
 >
 <svg class="h-10 w-10 -rotate-90 text-warm-white" viewBox="0 0 40 40">
 <circle cx="20" cy="20" r="16" fill="none" class="stroke-warm-white/30" stroke-width="4"></circle>
 <circle
 cx="20"
 cy="20"
 r="16"
 fill="none"
 class="stroke-current transition-[stroke-dashoffset] duration-150"
 stroke-width="4"
 stroke-linecap="round"
 stroke-dasharray="100.53"
 x-bind:stroke-dashoffset="uploadProgressOffset(attachment)"
 ></circle>
 </svg>
 </div>

 <div
 x-show="attachment.upload_state === 'complete'"
 class="absolute bottom-1 right-1 inline-flex h-7 w-7 items-center justify-center rounded-full bg-leaf text-white"
 aria-hidden="true"
 >
 <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
 <path d="m5 10 3 3 7-7"/>
 </svg>
 </div>
 </div>

 <div class="mt-2 min-w-0">
 <p class="truncate text-xs font-semibold text-bark" x-text="attachment.file_name"></p>
 <button
 type="button"
 class="mt-1 text-xs font-semibold text-fur transition hover:text-paw focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw"
 x-on:click="attachment.showAltText = !attachment.showAltText"
 x-text="attachment.showAltText ? 'Hide alt text' : 'Add alt text'"
 ></button>
 <div x-show="attachment.showAltText" x-transition class="mt-2">
 <input
 type="text"
 class="h-9 w-full rounded-[var(--radius-soft)] border border-whisker/40 bg-transparent px-3 text-xs text-bark placeholder:text-whisker focus:border-paw focus:outline-none focus:ring-2 focus:ring-paw/15"
 placeholder="Describe this image for screen readers."
 x-model.debounce.300ms="attachment.alt_text"
 x-on:input.debounce.350ms="updateAltText(attachment)"
 x-bind:aria-label="`Alt text for ${attachment.file_name}`"
 >
 </div>
 </div>
 </li>
 </template>
 </ul>
 </div>
 </div>

 <div class="flex flex-col gap-3 border-t border-whisker/30 pt-4 sm:flex-row sm:items-center sm:justify-between">
 <div class="min-h-5 text-xs text-fur" role="status">
 <span wire:loading.remove wire:target="autosaveDraft">
 @if ($draftId)
 Draft saved.
 @elseif ($isLinkPreviewLoading)
 Loading link preview...
 @else
 Ready to post.
 @endif
 </span>
 <span wire:loading wire:target="autosaveDraft">Saving draft...</span>
 </div>

 <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
 <x-ui.button type="button" variant="ghost" wire:click="autosaveDraft" wire:loading.attr="disabled" wire:target="autosaveDraft">
 Save draft
 </x-ui.button>
 <div class="relative" x-data="{ open: false }" x-on:keydown.escape.window="open = false">
 <button
 type="button"
 class="inline-flex h-[var(--control-height-md)] w-full items-center justify-center gap-2 rounded-[var(--radius-soft)] border border-whisker/40 bg-[color:var(--surface-form)] px-3 text-sm font-semibold text-bark transition hover:border-paw hover:bg-cream focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw sm:w-auto"
 x-on:click="open = !open"
 aria-label="Post visibility"
 aria-haspopup="true"
 x-bind:aria-expanded="open.toString()"
 >
 <span class="sr-only">Visibility</span>
 <span class="inline-flex h-5 w-5 items-center justify-center text-paw" aria-hidden="true">
 {!! $visibilityIcon($selectedVisibilityOption['value'], 'h-4 w-4') !!}
 </span>
 <span>{{ $selectedVisibilityOption['label'] }}</span>
 <svg class="h-4 w-4 text-fur transition" x-bind:class="{ 'rotate-180': open }" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
 <path fill-rule="evenodd" d="M5.22 7.72a.75.75 0 0 1 1.06 0L10 11.44l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 8.78a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/>
 </svg>
 </button>

 <div
 x-cloak
 x-show="open"
 x-transition.origin.bottom.right
 x-on:click.outside="open = false"
 class="absolute bottom-full right-0 z-30 mb-2 w-80 max-w-[calc(100vw-2rem)] overflow-hidden rounded-[var(--radius-card)] border border-whisker/30 bg-warm-white shadow-card"
 role="radiogroup"
 aria-label="Post visibility"
 >
 <div class="border-b border-whisker/20 px-4 py-3">
 <p class="text-sm font-semibold text-bark">Visibility</p>
 <p class="mt-0.5 text-xs text-fur">Choose who can see this post.</p>
 </div>

 <div class="space-y-2 p-2">
 @foreach ($visibilityOptions as $visibilityOption)
 @php
     $isSelectedVisibility = $selectedVisibility === $visibilityOption['value'];
 @endphp
 <button
 type="button"
 wire:click="selectVisibility('{{ $visibilityOption['value'] }}')"
 x-on:click="open = false"
 class="flex w-full items-start gap-3 rounded-[var(--radius-soft)] border px-3 py-2.5 text-left transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw {{ $isSelectedVisibility ? 'border-paw bg-paw/10 text-bark' : 'border-transparent text-bark hover:border-whisker/40 hover:bg-cream' }}"
 role="radio"
 aria-checked="{{ $isSelectedVisibility ? 'true' : 'false' }}"
 >
 <span class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full {{ $isSelectedVisibility ? 'bg-paw text-white' : 'bg-cream text-fur' }}" aria-hidden="true">
 {!! $visibilityIcon($visibilityOption['value'], 'h-4 w-4') !!}
 </span>
 <span class="min-w-0 flex-1">
 <span class="flex items-center gap-2">
 <span class="text-sm font-bold">{{ $visibilityOption['label'] }}</span>
 <span class="inline-flex h-4 w-4 shrink-0 items-center justify-center rounded-full border {{ $isSelectedVisibility ? 'border-paw bg-paw text-white' : 'border-whisker/60 text-transparent' }}" aria-hidden="true">
 <svg class="h-3 w-3" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
 <path d="m5 10 3 3 7-7"/>
 </svg>
 </span>
 </span>
 <span class="mt-1 block text-xs leading-5 text-fur">{{ $visibilityOption['description'] }}</span>
 </span>
 </button>
 @endforeach
 </div>
 </div>

 @if ($selectedVisibility === Post::VISIBILITY_PRIVATE)
 <p class="mt-1 text-xs font-medium text-fur" role="status">Only you will see this post</p>
 @endif
 </div>
 <x-ui.button
 type="submit"
 variant="primary"
 wire:loading.attr="disabled"
 wire:target="submit,confirmDuplicateAndSubmit"
 x-bind:disabled="characterCount > maxCharacters || hasActiveUploads"
 >
 <span wire:loading.remove wire:target="submit,confirmDuplicateAndSubmit">{{ filled($scheduledPublishAt) ? 'Schedule' : 'Post' }}</span>
 <span wire:loading wire:target="submit,confirmDuplicateAndSubmit">{{ filled($scheduledPublishAt) ? 'Scheduling...' : 'Posting...' }}</span>
 </x-ui.button>
 </div>
 </div>
 </form>
 </div>
 @if ($isModal)
 </div>
 </div>
 @else
 </section>
 @endif
</div>
