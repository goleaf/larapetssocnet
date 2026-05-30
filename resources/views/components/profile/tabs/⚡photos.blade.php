<?php

use App\Models\Content\Comment;
use App\Models\Content\Post;
use App\Models\Content\PostMedia;
use App\Models\Identity\User;
use App\Models\Pets\Pet;
use App\Services\CommentService;
use App\Services\ProfileVisibilityService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Component;

new class extends Component
{
    private const PHOTOS_PER_PAGE = 30;

    public int $profileUserId;

    /**
     * @var list<int>
     */
    public array $photoMediaIds = [];

    public ?int $nextPhotoCursor = null;

    public bool $hasMorePhotos = false;

    public bool $photosLoaded = false;

    public ?string $selectedPhotoKey = null;

    public function mount(int $profileUserId): void
    {
        $this->profileUserId = $profileUserId;
    }

    public function loadMorePhotos(): void
    {
        $profileUser = $this->profileUser();
        $viewer = $this->viewer();

        if (! app(ProfileVisibilityService::class)->canViewFullProfile($viewer, $profileUser)) {
            $this->selectedPhotoKey = null;
            $this->resetPhotos();

            return;
        }

        if ($this->photosLoaded && ! $this->hasMorePhotos) {
            return;
        }

        $this->appendPhotos($profileUser, $viewer, $this->photosLoaded ? $this->nextPhotoCursor : null);
    }

    public function openPhotoLightbox(string $photoKey): void
    {
        $photos = $this->lightboxPhotos();

        $this->selectedPhotoKey = $photos->contains(fn (array $photo): bool => $photo['key'] === $photoKey)
            ? $photoKey
            : null;
    }

    public function closePhotoLightbox(): void
    {
        $this->selectedPhotoKey = null;
    }

    public function showPreviousPhoto(): void
    {
        $this->movePhoto(-1);
    }

    public function showNextPhoto(): void
    {
        $this->movePhoto(1);
    }

    /**
     * @return array{
     *     profileUser: User,
     *     canViewPhotos: bool,
     *     photos: Collection<int, array{key: string, url: string, alt: string, post: Post, posted_at: string, posted_at_iso: ?string}>,
     *     selectedPhoto: ?array{key: string, url: string, alt: string, post: Post, posted_at: string, posted_at_iso: ?string},
     *     selectedPhotoIndex: ?int,
     *     selectedPhotoComments: Collection<int, Comment>,
     *     selectedPhotoTaggedPets: Collection<int, Pet>,
     *     selectedPhotoBodyHtml: string,
     *     selectedPostState: array<string, mixed>,
     *     hasPreviousPhoto: bool,
     *     hasNextPhoto: bool,
     *     hasMorePhotos: bool
     * }
     */
    public function viewData(): array
    {
        $profileUser = $this->profileUser();
        $viewer = $this->viewer();
        $canViewPhotos = app(ProfileVisibilityService::class)->canViewFullProfile($viewer, $profileUser);

        if ($canViewPhotos) {
            $this->ensurePhotosLoaded($profileUser, $viewer);
        } else {
            $this->selectedPhotoKey = null;
            $this->resetPhotos();
        }

        $photos = $canViewPhotos ? $this->postPhotos($profileUser, $viewer) : collect();
        [$selectedPhoto, $selectedPhotoIndex] = $this->selectedPhotoFrom($photos);
        $selectedPhotoComments = collect();
        $selectedPhotoTaggedPets = collect();
        $selectedPhotoBodyHtml = '';
        $selectedPostState = [];

        if (is_array($selectedPhoto)) {
            /** @var Post $selectedPost */
            $selectedPost = $selectedPhoto['post'];
            $selectedPhotoComments = app(CommentService::class)->threadForPost($selectedPost, $viewer);
            $selectedPhotoTaggedPets = $this->postTaggedPets($selectedPost, $viewer);
            $selectedPhotoBodyHtml = $this->postBodyHtml($selectedPost);
            $selectedPostState = $this->postState($selectedPost, $profileUser);
        }

        return [
            'profileUser' => $profileUser,
            'canViewPhotos' => $canViewPhotos,
            'photos' => $photos,
            'selectedPhoto' => $selectedPhoto,
            'selectedPhotoIndex' => $selectedPhotoIndex,
            'selectedPhotoComments' => $selectedPhotoComments,
            'selectedPhotoTaggedPets' => $selectedPhotoTaggedPets,
            'selectedPhotoBodyHtml' => $selectedPhotoBodyHtml,
            'selectedPostState' => $selectedPostState,
            'hasPreviousPhoto' => is_int($selectedPhotoIndex) && $selectedPhotoIndex > 0,
            'hasNextPhoto' => is_int($selectedPhotoIndex) && ($selectedPhotoIndex < $photos->count() - 1 || $this->hasMorePhotos),
            'hasMorePhotos' => $this->hasMorePhotos,
        ];
    }

    private function profileUser(): User
    {
        return User::query()
            ->whereKey($this->profileUserId)
            ->with('media')
            ->firstOrFail();
    }

    private function viewer(): ?User
    {
        $viewer = auth()->user();

        return $viewer instanceof User ? $viewer : null;
    }

    /**
     * @return Collection<int, array{key: string, url: string, alt: string, post: Post, posted_at: string, posted_at_iso: ?string}>
     */
    private function postPhotos(User $profileUser, ?User $viewer): Collection
    {
        return Post::profilePhotoMediaByIds($profileUser, $viewer, $this->photoMediaIds)
            ->map(function (PostMedia $mediaItem) use ($profileUser): ?array {
                $post = $mediaItem->post;

                if (! $post instanceof Post) {
                    return null;
                }

                $url = Post::mediaItemUrl($mediaItem);

                if ($url === '') {
                    return null;
                }

                $postedAt = $post->created_at?->format('M j, Y') ?? '';

                return [
                    'key' => $this->photoKey($post, $mediaItem),
                    'url' => $url,
                    'alt' => __('Photo from :name\'s post', ['name' => $profileUser->name]),
                    'post' => $post,
                    'posted_at' => $postedAt,
                    'posted_at_iso' => $post->created_at?->toIso8601String(),
                ];
            })
            ->filter()
            ->values();
    }

    private function photoKey(Post $post, mixed $mediaItem): string
    {
        $mediaKey = is_object($mediaItem) && method_exists($mediaItem, 'getKey')
            ? (string) $mediaItem->getKey()
            : md5(Post::mediaItemUrl($mediaItem));

        $mediaType = is_object($mediaItem)
            ? Str::kebab(class_basename($mediaItem::class))
            : 'media';

        return sprintf('profile-photo-%s-%s-%s', $post->getKey(), $mediaType, $mediaKey);
    }

    /**
     * @return Collection<int, array{key: string, url: string, alt: string, post: Post, posted_at: string, posted_at_iso: ?string}>
     */
    private function lightboxPhotos(): Collection
    {
        $profileUser = $this->profileUser();
        $viewer = $this->viewer();

        if (! app(ProfileVisibilityService::class)->canViewFullProfile($viewer, $profileUser)) {
            return collect();
        }

        $this->ensurePhotosLoaded($profileUser, $viewer);

        return $this->postPhotos($profileUser, $viewer);
    }

    private function movePhoto(int $direction): void
    {
        if ($this->selectedPhotoKey === null) {
            return;
        }

        $photos = $this->lightboxPhotos();
        $selectedPhotoIndex = $photos->search(fn (array $photo): bool => $photo['key'] === $this->selectedPhotoKey);

        if (! is_int($selectedPhotoIndex)) {
            $this->selectedPhotoKey = null;

            return;
        }

        $nextIndex = $selectedPhotoIndex + $direction;

        if (! $photos->has($nextIndex) && $direction > 0 && $this->hasMorePhotos) {
            $profileUser = $this->profileUser();
            $viewer = $this->viewer();

            $this->appendPhotos($profileUser, $viewer, $this->nextPhotoCursor);

            $photos = $this->lightboxPhotos();
            $selectedPhotoIndex = $photos->search(fn (array $photo): bool => $photo['key'] === $this->selectedPhotoKey);

            if (! is_int($selectedPhotoIndex)) {
                $this->selectedPhotoKey = null;

                return;
            }

            $nextIndex = $selectedPhotoIndex + $direction;
        }

        if (! $photos->has($nextIndex)) {
            return;
        }

        $nextPhoto = $photos->get($nextIndex);
        $this->selectedPhotoKey = is_array($nextPhoto) ? $nextPhoto['key'] : null;
    }

    /**
     * @param  Collection<int, array{key: string, url: string, alt: string, post: Post, posted_at: string, posted_at_iso: ?string}>  $photos
     * @return array{0: ?array{key: string, url: string, alt: string, post: Post, posted_at: string, posted_at_iso: ?string}, 1: ?int}
     */
    private function selectedPhotoFrom(Collection $photos): array
    {
        if ($this->selectedPhotoKey === null) {
            return [null, null];
        }

        $selectedPhotoIndex = $photos->search(fn (array $photo): bool => $photo['key'] === $this->selectedPhotoKey);

        if (! is_int($selectedPhotoIndex)) {
            $this->selectedPhotoKey = null;

            return [null, null];
        }

        $selectedPhoto = $photos->get($selectedPhotoIndex);

        return [is_array($selectedPhoto) ? $selectedPhoto : null, $selectedPhotoIndex];
    }

    private function ensurePhotosLoaded(User $profileUser, ?User $viewer): void
    {
        if ($this->photosLoaded) {
            return;
        }

        $this->appendPhotos($profileUser, $viewer);
    }

    private function appendPhotos(User $profileUser, ?User $viewer, ?int $cursor = null): void
    {
        $mediaPage = Post::profilePhotoMediaPage($profileUser, $viewer, self::PHOTOS_PER_PAGE, $cursor);
        $visiblePageItems = $mediaPage->take(self::PHOTOS_PER_PAGE);

        foreach ($visiblePageItems as $mediaItem) {
            $mediaId = (int) $mediaItem->getKey();

            if (! in_array($mediaId, $this->photoMediaIds, true)) {
                $this->photoMediaIds[] = $mediaId;
            }
        }

        $lastMediaItem = $visiblePageItems->last();
        $this->nextPhotoCursor = $lastMediaItem instanceof PostMedia ? (int) $lastMediaItem->getKey() : null;
        $this->hasMorePhotos = $mediaPage->count() > self::PHOTOS_PER_PAGE;
        $this->photosLoaded = true;
    }

    private function resetPhotos(): void
    {
        $this->photoMediaIds = [];
        $this->nextPhotoCursor = null;
        $this->hasMorePhotos = false;
        $this->photosLoaded = false;
    }

    /**
     * @return Collection<int, Pet>
     */
    private function postTaggedPets(Post $post, ?User $viewer): Collection
    {
        $taggedPetIds = collect($post->tagged_pets ?? [])
            ->filter(fn (mixed $petId): bool => is_numeric($petId))
            ->map(fn (mixed $petId): int => (int) $petId);

        if ($post->pet_id) {
            $taggedPetIds->push((int) $post->pet_id);
        }

        $taggedPetIds = $taggedPetIds->unique()->values();

        if ($taggedPetIds->isEmpty()) {
            return collect();
        }

        $positionById = $taggedPetIds->flip();

        return Pet::query()
            ->visibleTo($viewer)
            ->whereIn('pets.id', $taggedPetIds->all())
            ->get()
            ->sortBy(fn (Pet $pet): int => (int) ($positionById->get($pet->getKey()) ?? PHP_INT_MAX))
            ->values();
    }

    private function postBodyHtml(Post $post): string
    {
        $body = trim((string) $post->body);
        $storedBodyHtml = (string) ($post->body_html ?? '');
        $storedBodyText = trim(html_entity_decode(strip_tags($storedBodyHtml)));

        if ($storedBodyHtml !== '' && ($body === '' || $storedBodyText === $body)) {
            return $storedBodyHtml;
        }

        return nl2br(e($body));
    }

    /**
     * @return array<string, mixed>
     */
    private function postState(Post $post, User $profileUser): array
    {
        $rawReaction = $post->getAttribute('current_user_reaction_type');
        $currentReaction = filled($rawReaction)
            ? \App\Models\Content\Reaction::normalizeType((string) $rawReaction)
            : null;

        if ($currentReaction === null && (bool) ($post->liked_by_viewer ?? false)) {
            $currentReaction = \App\Models\Content\Reaction::defaultType();
        }

        return [
            'authorName' => $profileUser->name,
            'liked' => (bool) ($post->liked_by_viewer ?? false),
            'reaction' => $currentReaction,
            'reactionOptions' => \App\Models\Content\Reaction::options(),
            'defaultReaction' => \App\Models\Content\Reaction::defaultType(),
            'reactionCounts' => \App\Models\Content\Reaction::countMapForModel($post),
            'topReactions' => \App\Models\Content\Reaction::topCountsForModel($post, 3),
            'likes' => (int) ($post->reactions_count ?? $post->likes_count ?? 0),
            'saved' => (bool) ($post->saved_by_viewer ?? false),
            'saveCount' => (int) ($post->save_count ?? 0),
            'shares' => (int) ($post->shares_count ?? 0),
            'reactionUrl' => route('posts.react', $post),
            'likeUrl' => route('posts.like', $post),
            'saveUrl' => route('posts.save', $post),
            'shareUrl' => route('posts.share', $post),
            'showUrl' => route('posts.show', $post),
        ];
    }
};
?>

@placeholder
<div data-ui="profile-tab-panel-loading" id="profile-panel-photos" aria-busy="true">
 <x-ui.card>
 <div class="grid grid-cols-2 gap-3 lg:grid-cols-3">
 @for ($index = 0; $index < 8; $index++)
 <div class="aspect-square animate-pulse rounded-[var(--radius-soft)] bg-cream"></div>
 @endfor
 </div>
 </x-ui.card>
</div>
@endplaceholder

@php
 $data = $this->viewData();
 $closeIcon = '<path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />';
 $chevronLeftIcon = '<path stroke-linecap="round" stroke-linejoin="round" d="m15 18-6-6 6-6" />';
 $chevronRightIcon = '<path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6" />';
@endphp

<div data-ui="profile-tab-panel" id="profile-panel-photos">
 @if ($data['canViewPhotos'])
 <x-ui.card>
 @if ($data['photos']->isNotEmpty())
 <div data-ui="profile-photos-grid" class="grid grid-cols-2 gap-2 lg:grid-cols-3">
 @foreach ($data['photos'] as $photo)
 <button
 type="button"
 wire:key="{{ $photo['key'] }}"
 wire:click="openPhotoLightbox('{{ $photo['key'] }}')"
 wire:loading.attr="disabled"
 wire:target="openPhotoLightbox"
 data-ui="profile-photo-grid-item"
 class="group relative aspect-square overflow-hidden rounded-[var(--radius-soft)] border border-whisker/30 bg-cream text-left transition-[border-color,scale,box-shadow] duration-150 hover:border-paw/50 hover:shadow-md focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw"
 aria-haspopup="dialog"
 aria-label="{{ __('Open photo from :name\'s post', ['name' => $data['profileUser']->name]) }}"
 >
 <img src="{{ $photo['url'] }}" alt="{{ $photo['alt'] }}" class="h-full w-full object-cover transition-[scale] duration-200 lg:group-hover:scale-[1.02]" loading="lazy">
 <span class="pointer-events-none absolute inset-0 hidden items-center justify-center bg-bark/0 text-white opacity-0 transition-[background-color,opacity] duration-150 lg:flex lg:group-hover:bg-bark/45 lg:group-hover:opacity-100">
 <span class="inline-flex items-center gap-3 rounded-full bg-bark/60 px-3 py-1.5 text-xs font-semibold shadow-sm backdrop-blur-sm">
 <span>{{ number_format((int) ($photo['post']->reactions_count ?? $photo['post']->likes_count ?? 0)) }} reactions</span>
 <span>{{ number_format((int) ($photo['post']->comments_count ?? 0)) }} comments</span>
 </span>
 </span>
 </button>
 @endforeach
 </div>
 @if ($data['hasMorePhotos'])
 <div data-ui="profile-photos-infinite-scroll-trigger" wire:intersect.margin.600px="loadMorePhotos" aria-live="polite" class="mt-3">
 <div wire:loading.block wire:target="loadMorePhotos" data-ui="profile-photos-loading-skeleton" role="status" aria-label="Loading more photos" class="grid grid-cols-2 gap-2 lg:grid-cols-3">
 @for ($index = 0; $index < 6; $index++)
 <div class="aspect-square animate-pulse rounded-[var(--radius-soft)] bg-cream"></div>
 @endfor
 </div>
 <div wire:loading.remove wire:target="loadMorePhotos" class="h-8" aria-hidden="true"></div>
 </div>
 @endif
 @else
 <x-ui.empty-state icon="📷" title="No photos yet"
 description="When this user shares visible post photos, they will appear here."/>
 @endif
 </x-ui.card>
 @else
 <x-ui.card>
 <x-ui.empty-state icon="🔒" title="Photos are private"
 description="This profile does not share photos with your current access level."/>
 </x-ui.card>
 @endif

 @if (is_array($data['selectedPhoto']))
 @php
 $selectedPhoto = $data['selectedPhoto'];
 $selectedPost = $selectedPhoto['post'];
 $selectedAuthor = $selectedPost->user ?? $selectedPost->author ?? $data['profileUser'];
 $selectedAuthorName = $selectedAuthor?->name ?? $data['profileUser']->name;
 $selectedAuthorUsername = $selectedAuthor?->username ?? $data['profileUser']->username;
 $selectedAuthorAvatar = $selectedAuthor?->avatar_url ?? $data['profileUser']->avatar_url;
 $selectedPostUrl = route('posts.show', $selectedPost);
 $selectedPostTime = $selectedPost->created_at;
 $selectedPostTimeLabel = $selectedPostTime?->diffForHumans();
 $selectedPostTimeIso = $selectedPostTime?->toIso8601String();
 $selectedPostTimeTitle = $selectedPostTime?->format('M j, Y g:i A');
 $reactionCount = (int) ($selectedPost->likes_count ?? $selectedPost->reactions_count ?? 0);
 $commentCount = (int) ($selectedPost->comments_count ?? 0);
 @endphp

 <div
 data-ui="profile-photo-lightbox-modal"
 class="fixed inset-0 z-50 overflow-y-auto"
 role="dialog"
 aria-modal="true"
 aria-labelledby="profile-photo-lightbox-title"
 x-data="profilePhotoLightbox()"
 x-init="focusClose()"
 @keydown.window="handleKeydown($event, $wire)"
 @touchstart.passive="startSwipe($event)"
 @touchend.passive="finishSwipe($event, $wire)"
 >
 <button type="button" class="fixed inset-0 bg-bark/70" wire:click="closePhotoLightbox" tabindex="-1">
 <span class="sr-only">Close photo lightbox</span>
 </button>

 <div class="relative mx-auto flex min-h-full w-full max-w-6xl items-center justify-center p-3 sm:p-6">
 <div class="relative grid max-h-[calc(100vh-1.5rem)] w-full overflow-hidden rounded-[var(--radius-card)] bg-[color:var(--surface-modal)] shadow-2xl lg:grid-cols-[minmax(0,1fr)_minmax(22rem,26rem)]">
 <section data-ui="profile-photo-lightbox-media" class="relative flex min-h-[52vh] items-center justify-center bg-bark lg:min-h-[calc(100vh-5rem)]">
 <img src="{{ $selectedPhoto['url'] }}" alt="{{ $selectedPhoto['alt'] }}" class="max-h-[62vh] w-full object-contain lg:max-h-[calc(100vh-5rem)]" loading="eager">

 @if ($data['hasPreviousPhoto'])
 <x-ui.icon-button
 type="button"
 size="lg"
 variant="ghost"
 :icon="$chevronLeftIcon"
 label="Previous photo"
 wire:click="showPreviousPhoto"
 wire:loading.attr="disabled"
 wire:target="showPreviousPhoto,showNextPhoto"
 class="absolute left-3 top-1/2 -translate-y-1/2 border border-white/20 bg-bark/50 text-white backdrop-blur-sm hover:bg-bark/70 focus-visible:outline-white"
 data-ui="profile-photo-lightbox-previous"
 />
 @endif

 @if ($data['hasNextPhoto'])
 <x-ui.icon-button
 type="button"
 size="lg"
 variant="ghost"
 :icon="$chevronRightIcon"
 label="Next photo"
 wire:click="showNextPhoto"
 wire:loading.attr="disabled"
 wire:target="showPreviousPhoto,showNextPhoto"
 class="absolute right-3 top-1/2 -translate-y-1/2 border border-white/20 bg-bark/50 text-white backdrop-blur-sm hover:bg-bark/70 focus-visible:outline-white"
 data-ui="profile-photo-lightbox-next"
 />
 @endif
 </section>

 <aside data-ui="profile-photo-lightbox-context" class="flex max-h-[calc(100vh-1.5rem)] min-w-0 flex-col overflow-y-auto bg-[color:var(--surface-modal)]">
 <div class="flex items-start justify-between gap-4 border-b border-whisker/40 px-4 py-3">
 <div class="min-w-0">
 <h3 id="profile-photo-lightbox-title" class="text-lg font-semibold font-display text-bark">Photo</h3>
 <p class="text-xs text-fur">
 {{ ($data['selectedPhotoIndex'] ?? 0) + 1 }} of {{ $data['photos']->count() }}
 </p>
 </div>
 <x-ui.icon-button
 type="button"
 size="sm"
 variant="ghost"
 :icon="$closeIcon"
 label="Close photo lightbox"
 wire:click="closePhotoLightbox"
 x-ref="closeButton"
 data-ui="profile-photo-lightbox-close"
 />
 </div>

 <div class="flex flex-col gap-4 px-4 py-4">
 <header class="flex items-start gap-3">
 <a href="{{ route('profile.show', $selectedAuthorUsername) }}" class="shrink-0 rounded-[var(--radius-soft)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
 <x-ui.avatar :src="$selectedAuthorAvatar" :name="$selectedAuthorName" :user="$selectedAuthor" size="md"/>
 </a>
 <div class="min-w-0">
 <a href="{{ route('profile.show', $selectedAuthorUsername) }}" class="block truncate text-sm font-semibold text-bark hover:text-paw focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
 {{ $selectedAuthorName }}
 </a>
 <p class="truncate text-xs text-fur">&#64;{{ $selectedAuthorUsername }}</p>
 @if ($selectedPostTimeIso && $selectedPostTimeLabel)
 <time datetime="{{ $selectedPostTimeIso }}" title="{{ $selectedPostTimeTitle }}" class="mt-1 block text-xs text-fur">{{ $selectedPostTimeLabel }}</time>
 @endif
 </div>
 </header>

 @if ($data['selectedPhotoBodyHtml'] !== '')
 <div data-ui="profile-photo-lightbox-post-text" class="whitespace-pre-line text-sm leading-6 text-bark">
 {!! $data['selectedPhotoBodyHtml'] !!}
 </div>
 @endif

 @if ($data['selectedPhotoTaggedPets']->isNotEmpty() || filled((string) $selectedPost->location))
 <div class="flex flex-wrap gap-2" data-ui="profile-photo-lightbox-tags">
 @foreach ($data['selectedPhotoTaggedPets'] as $taggedPet)
 <a href="{{ route('pets.show', $taggedPet->slug ?? $taggedPet->getKey()) }}" class="ui-token hover:border-paw/50 hover:text-paw focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
 <span aria-hidden="true">🐾</span>
 <span>{{ $taggedPet->name }}</span>
 </a>
 @endforeach

 @if ($selectedPost->location)
 <span class="ui-token">
 <span aria-hidden="true">📍</span>
 <span>{{ $selectedPost->location }}</span>
 </span>
 @endif
 </div>
 @endif

 <div
 data-ui="profile-photo-lightbox-reaction-bar"
 class="rounded-[var(--radius-card)] border border-whisker/40 bg-cream/30 p-3"
 x-data="postCard({{ \Illuminate\Support\Js::from($data['selectedPostState']) }})"
 >
 <div class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:items-center">
 @auth
 <x-ui.button
 type="button"
 size="sm"
 variant="outline"
 class="min-h-11 w-full sm:w-auto"
 aria-label="{{ __('React to post by :name', ['name' => $selectedAuthorName]) }}"
 @click="toggleLike()"
 x-bind:disabled="likeBusy"
 x-bind:aria-label="(liked ? 'Remove reaction from post by ' : 'React to post by ') + authorName"
 x-bind:aria-pressed="liked"
 x-bind:aria-busy="likeBusy"
 x-bind:class="liked ? activeReactionClass() : ''"
 >
 <span aria-hidden="true" x-text="activeReactionEmoji()"></span>
 <span x-text="activeReactionLabel()"></span>
 <span class="opacity-80" aria-live="polite" x-text="likes">{{ number_format($reactionCount) }}</span>
 </x-ui.button>
 @else
 <x-ui.button :href="route('login')" size="sm" variant="outline" class="min-h-11 w-full sm:w-auto">
 Log in to react
 </x-ui.button>
 @endauth

 <x-ui.button
 :href="$selectedPostUrl.'#comments'"
 size="sm"
 variant="ghost"
 class="min-h-11 w-full sm:w-auto"
 aria-label="{{ __('Read comments on post by :name', ['name' => $selectedAuthorName]) }}"
 >
 <span aria-hidden="true">💬</span>
 <span>Comments</span>
 <span class="opacity-80">({{ number_format($commentCount) }})</span>
 </x-ui.button>
 </div>
 <p class="mt-2 text-xs text-fur">
 {{ trans_choice(':count reaction|:count reactions', $reactionCount, ['count' => number_format($reactionCount)]) }}
 ·
 {{ trans_choice(':count comment|:count comments', $commentCount, ['count' => number_format($commentCount)]) }}
 </p>
 </div>

 <section class="rounded-[var(--radius-card)] border border-whisker/40 bg-cream/30 p-3" data-ui="profile-photo-lightbox-comments">
 <div class="flex items-center justify-between gap-3">
 <h4 class="text-sm font-semibold text-bark">Comments</h4>
 <span class="text-sm text-fur">{{ number_format($commentCount) }}</span>
 </div>

 @forelse ($data['selectedPhotoComments'] as $comment)
 <x-comment-item :comment="$comment" :post="$selectedPost"/>
 @empty
 <x-ui.empty-state title="No comments yet" description="Be the first to share your thoughts!" icon="💬"/>
 @endforelse
 </section>
 </div>
 </aside>
 </div>
 </div>
 </div>
 @endif
</div>
