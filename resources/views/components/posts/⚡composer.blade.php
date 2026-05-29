<?php

use App\Actions\Posts\CreatePostAction;
use App\Enums\PostStatus;
use App\Models\Content\Post;
use App\Models\Content\PostDraft;
use App\Models\Identity\User;
use App\Models\Pets\Pet;
use App\Services\PostDraftService;
use App\Support\Posts\PostMood;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
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
     * @var list<array{temporary_path: string, preview_data_url: ?string, file_name: string, media_type: string, alt_text: ?string}>
     */
    public array $attachmentMetadata = [];

    /**
     * @var list<int>
     */
    public array $selectedPetIds = [];

    public ?string $locationDisplayText = null;

    public ?string $locationLat = null;

    public ?string $locationLng = null;

    public ?string $selectedMood = null;

    public string $selectedVisibility = Post::VISIBILITY_PUBLIC;

    public ?string $scheduledPublishAt = null;

    /**
     * @var array<string, mixed>
     */
    public array $linkPreviewData = [];

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

    /**
     * @param  list<int>|null  $selectedPetIds
     */
    public function mount(
        string $mode = self::MODE_INLINE,
        ?array $selectedPetIds = null,
        ?string $visibility = null,
        ?string $contextType = null,
        int $contextId = 0,
    ): void {
        $this->mode = in_array($mode, [self::MODE_INLINE, self::MODE_MODAL], true) ? $mode : self::MODE_INLINE;
        $this->contextType = filled($contextType) ? (string) $contextType : 'default';
        $this->contextId = max(0, $contextId);
        $this->selectedVisibility = $this->normalizeVisibility($visibility) ?? $this->defaultVisibility();
        $this->selectedPetIds = $this->normalizePetIds($selectedPetIds ?? []);

        $this->restoreDraft();
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
            ->select(['id', 'name'])
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

    public function updatedMediaUploads(): void
    {
        $this->syncAttachmentMetadata();
    }

    public function updateAttachmentAltText(int $index, string $altText): void
    {
        if (! array_key_exists($index, $this->attachmentMetadata)) {
            return;
        }

        $this->attachmentMetadata[$index]['alt_text'] = trim(mb_substr($altText, 0, 160)) ?: null;
    }

    public function removeAttachment(int $index): void
    {
        if (! array_key_exists($index, $this->attachmentMetadata)) {
            return;
        }

        array_splice($this->attachmentMetadata, $index, 1);
        array_splice($this->temporaryFilePaths, $index, 1);
        array_splice($this->mediaUploads, $index, 1);
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
        $this->locationLat = $draft->location_lat === null ? null : (string) $draft->location_lat;
        $this->locationLng = $draft->location_lng === null ? null : (string) $draft->location_lng;
        $this->selectedPetIds = $this->normalizePetIds($draft->tagged_pets ?? []);
        $this->linkPreviewData = is_array($draft->link_preview) ? $draft->link_preview : [];
        $this->scheduledPublishAt = $draft->scheduled_publish_at?->format('Y-m-d\TH:i');
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
     * @return list<array{temporary_path: string, media_type: string, alt_text: ?string}>
     */
    private function mediaAttachmentPayload(): array
    {
        return collect($this->attachmentMetadata)
            ->filter(fn (array $attachment): bool => filled($attachment['temporary_path'] ?? null))
            ->map(fn (array $attachment): array => [
                'temporary_path' => (string) $attachment['temporary_path'],
                'media_type' => (string) ($attachment['media_type'] ?? 'image'),
                'alt_text' => filled($attachment['alt_text'] ?? null) ? (string) $attachment['alt_text'] : null,
            ])
            ->values()
            ->all();
    }

    private function syncAttachmentMetadata(): void
    {
        $attachments = collect($this->mediaUploads)
            ->filter(fn (mixed $file): bool => $file instanceof TemporaryUploadedFile)
            ->map(function (TemporaryUploadedFile $file): array {
                $temporaryPath = $file->getRealPath() ?: $file->getPathname();
                $mediaType = str_starts_with((string) $file->getMimeType(), 'video/') ? 'video' : 'image';

                return [
                    'temporary_path' => $temporaryPath,
                    'preview_data_url' => $this->temporaryPreviewUrl($file, $mediaType),
                    'file_name' => $file->getClientOriginalName(),
                    'media_type' => $mediaType,
                    'alt_text' => null,
                ];
            })
            ->values()
            ->all();

        $this->attachmentMetadata = $attachments;
        $this->temporaryFilePaths = collect($attachments)
            ->pluck('temporary_path')
            ->filter()
            ->values()
            ->all();
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
        $this->selectedPetIds = [];
        $this->locationDisplayText = null;
        $this->locationLat = null;
        $this->locationLng = null;
        $this->selectedMood = null;
        $this->scheduledPublishAt = null;
        $this->linkPreviewData = [];
        $this->draftId = null;
        $this->duplicateDetected = false;
        $this->duplicatePostId = null;
        $this->confirmedDuplicate = false;
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
@endphp

<div
 x-data="postComposer({ text: @js($textContent), maxCharacters: 1000 })"
 x-on:livewire-upload-start="$wire.isUploading = true"
 x-on:livewire-upload-finish="$wire.isUploading = false"
 x-on:livewire-upload-cancel="$wire.isUploading = false"
 x-on:livewire-upload-error="$wire.isUploading = false"
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
 <form wire:submit="submit" class="space-y-5">
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
 x-on:paste.debounce.150ms="syncFromEditor"
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

 <div class="grid gap-4 md:grid-cols-2">
 <x-ui.select id="{{ $composerId }}-visibility" label="Visibility" wire:model="selectedVisibility">
 <option value="public">Public</option>
 <option value="followers">Followers</option>
 <option value="friends">Friends</option>
 <option value="private">Only me</option>
 </x-ui.select>

 <x-ui.select id="{{ $composerId }}-mood" label="Mood" wire:model="selectedMood">
 <option value="">No mood</option>
 @foreach (PostMood::DISPLAY as $moodValue => $moodDisplay)
 <option value="{{ $moodValue }}">{{ $moodDisplay['emoji'] }} {{ $moodDisplay['label'] }}</option>
 @endforeach
 </x-ui.select>
 </div>

 <div class="grid gap-4 md:grid-cols-2">
 <x-ui.select id="{{ $composerId }}-pets" label="Tag pets" wire:model="selectedPetIds" multiple>
 @forelse ($this->availablePets as $pet)
 <option value="{{ $pet->id }}">{{ $pet->name }}</option>
 @empty
 <option value="" disabled>No pets yet</option>
 @endforelse
 </x-ui.select>

 <x-ui.input id="{{ $composerId }}-location" label="Location" wire:model.blur="locationDisplayText" maxlength="100" />
 </div>

 <div class="grid gap-4 md:grid-cols-3">
 <x-ui.input id="{{ $composerId }}-lat" label="Latitude" wire:model.blur="locationLat" inputmode="decimal" />
 <x-ui.input id="{{ $composerId }}-lng" label="Longitude" wire:model.blur="locationLng" inputmode="decimal" />
 <x-ui.input id="{{ $composerId }}-scheduled" label="Schedule" type="datetime-local" wire:model.blur="scheduledPublishAt" />
 </div>

 <div class="rounded-[var(--radius-soft)] border border-whisker/30 bg-cream/45 p-4">
 <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
 <div>
 <label for="{{ $composerId }}-media" class="text-sm font-semibold text-bark">Media</label>
 <p class="mt-1 text-xs leading-5 text-fur">Attach up to 5 images or one video.</p>
 </div>
 <input
 id="{{ $composerId }}-media"
 type="file"
 wire:model="mediaUploads"
 multiple
 accept="image/jpeg,image/png,image/gif,image/webp,video/mp4,video/quicktime"
 class="block w-full cursor-pointer text-sm text-fur file:mr-4 file:min-h-10 file:rounded-[var(--radius-soft)] file:border-0 file:bg-paw/10 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-paw hover:file:bg-paw/20 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw sm:max-w-xs"
 x-on:change="previewSelectedFiles"
 />
 </div>

 <div wire:loading wire:target="mediaUploads" class="mt-3 text-xs font-semibold text-paw" role="status">
 Uploading media...
 </div>

 @error('media_attachments')
 <p class="mt-2 text-sm font-medium text-rose">{{ $message }}</p>
 @enderror
 @error('media')
 <p class="mt-2 text-sm font-medium text-rose">{{ $message }}</p>
 @enderror

 @if ($attachmentMetadata !== [])
 <ul class="mt-4 grid gap-3 sm:grid-cols-2" aria-label="Selected attachments">
 @foreach ($attachmentMetadata as $index => $attachment)
 <li wire:key="composer-attachment-{{ $index }}-{{ md5((string) $attachment['temporary_path']) }}" class="rounded-[var(--radius-soft)] border border-whisker/30 bg-warm-white p-3">
 <div class="flex items-start gap-3">
 <div class="h-14 w-14 shrink-0 overflow-hidden rounded-[var(--radius-soft)] bg-cream">
 @if (($attachment['media_type'] ?? 'image') === 'image' && filled($attachment['preview_data_url'] ?? null))
 <img src="{{ $attachment['preview_data_url'] }}" alt="" class="h-full w-full object-cover">
 @else
 <div class="flex h-full w-full items-center justify-center text-xs font-semibold uppercase text-fur">Video</div>
 @endif
 </div>
 <div class="min-w-0 flex-1">
 <p class="truncate text-sm font-semibold text-bark">{{ $attachment['file_name'] }}</p>
 <input
 type="text"
 value="{{ $attachment['alt_text'] ?? '' }}"
 wire:change="updateAttachmentAltText({{ $index }}, $event.target.value)"
 class="mt-2 h-9 w-full rounded-[var(--radius-soft)] border border-whisker/40 bg-transparent px-3 text-xs text-bark placeholder:text-whisker focus:border-paw focus:outline-none focus:ring-2 focus:ring-paw/15"
 placeholder="Alt text"
 aria-label="Alt text for {{ $attachment['file_name'] }}"
 >
 </div>
 <button type="button" class="text-xs font-semibold text-rose hover:text-red-700" wire:click="removeAttachment({{ $index }})">Remove</button>
 </div>
 </li>
 @endforeach
 </ul>
 @endif
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
 <x-ui.button
 type="submit"
 variant="primary"
 wire:loading.attr="disabled"
 wire:target="submit,confirmDuplicateAndSubmit,mediaUploads"
 x-bind:disabled="characterCount > maxCharacters"
 >
 <span wire:loading.remove wire:target="submit,confirmDuplicateAndSubmit">Post</span>
 <span wire:loading wire:target="submit,confirmDuplicateAndSubmit">Posting...</span>
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
