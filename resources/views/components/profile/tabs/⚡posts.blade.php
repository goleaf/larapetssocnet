<?php

use App\Models\Content\Comment;
use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Services\CommentService;
use App\Services\ProfileVisibilityService;
use Illuminate\Support\Collection;
use Livewire\Component;

new class extends Component
{
    private const POSTS_PER_PAGE = 15;

    public int $profileUserId;

    /**
     * @var list<int>
     */
    public array $postIds = [];

    public ?string $nextCursor = null;

    public bool $hasMorePosts = false;

    public bool $postsLoaded = false;

    public bool $mediaOnly = false;

    public ?int $selectedPostId = null;

    public function mount(int $profileUserId): void
    {
        $this->profileUserId = $profileUserId;
    }

    public function loadMorePosts(): void
    {
        $profileUser = $this->profileUser();
        $viewer = $this->viewer();

        if (! app(ProfileVisibilityService::class)->canViewFullProfile($viewer, $profileUser)) {
            $this->resetTimeline();

            return;
        }

        if ($this->postsLoaded && ! $this->hasMorePosts) {
            return;
        }

        $this->appendTimelinePosts($profileUser, $viewer, $this->postsLoaded ? $this->nextCursor : null);
    }

    public function toggleMediaOnly(): void
    {
        $this->mediaOnly = ! $this->mediaOnly;
        $this->selectedPostId = null;
        $this->resetTimeline();
    }

    public function openMediaPost(int $postId): void
    {
        $profileUser = $this->profileUser();
        $viewer = $this->viewer();

        if (! app(ProfileVisibilityService::class)->canViewFullProfile($viewer, $profileUser)) {
            $this->selectedPostId = null;

            return;
        }

        $post = Post::profileTimelinePostForModal($profileUser, $viewer, $postId, true);

        $this->selectedPostId = $post instanceof Post ? (int) $post->getKey() : null;
    }

    public function closePostModal(): void
    {
        $this->selectedPostId = null;
    }

    /**
     * @return array{
     *     profileUser: User,
     *     isOwner: bool,
     *     canViewContent: bool,
     *     mediaOnly: bool,
     *     pinnedPost: ?Post,
     *     posts: Collection<int, Post>,
     *     hasMorePosts: bool,
     *     selectedPost: ?Post,
     *     selectedPostComments: Collection<int, Comment>
     * }
     */
    public function viewData(): array
    {
        $profileUser = $this->profileUser();
        $viewer = $this->viewer();
        $isOwner = $viewer instanceof User && $viewer->is($profileUser);
        $canViewContent = app(ProfileVisibilityService::class)->canViewFullProfile($viewer, $profileUser);

        if ($canViewContent) {
            $this->ensureTimelineLoaded($profileUser, $viewer);
        } else {
            $this->selectedPostId = null;
            $this->resetTimeline();
        }

        $posts = $canViewContent
            ? Post::profileTimelinePostsByIds($profileUser, $viewer, $this->postIds, $this->mediaOnly)
            : collect();
        $pinnedPost = $canViewContent && ! $this->mediaOnly
            ? Post::pinnedProfileTimelinePost($profileUser, $viewer)
            : null;
        $selectedPost = null;
        $selectedPostComments = collect();

        if ($canViewContent && $this->selectedPostId !== null) {
            $selectedPost = Post::profileTimelinePostForModal($profileUser, $viewer, $this->selectedPostId, true);

            if ($selectedPost instanceof Post) {
                $selectedPostComments = app(CommentService::class)->threadForPost($selectedPost, $viewer);
            } else {
                $this->selectedPostId = null;
            }
        }

        return [
            'profileUser' => $profileUser,
            'isOwner' => $isOwner,
            'canViewContent' => $canViewContent,
            'mediaOnly' => $this->mediaOnly,
            'pinnedPost' => $pinnedPost,
            'posts' => $posts,
            'hasMorePosts' => $this->hasMorePosts,
            'selectedPost' => $selectedPost,
            'selectedPostComments' => $selectedPostComments,
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

    private function ensureTimelineLoaded(User $profileUser, ?User $viewer): void
    {
        if ($this->postsLoaded) {
            return;
        }

        $this->appendTimelinePosts($profileUser, $viewer);
    }

    private function appendTimelinePosts(User $profileUser, ?User $viewer, ?string $cursor = null): void
    {
        $posts = Post::paginateProfileTimeline($profileUser, $viewer, self::POSTS_PER_PAGE, $cursor, $this->mediaOnly);

        foreach ($posts->items() as $post) {
            $postId = (int) $post->getKey();

            if (! in_array($postId, $this->postIds, true)) {
                $this->postIds[] = $postId;
            }
        }

        $this->nextCursor = $posts->nextCursor()?->encode();
        $this->hasMorePosts = $posts->hasMorePages();
        $this->postsLoaded = true;
    }

    private function resetTimeline(): void
    {
        $this->postIds = [];
        $this->nextCursor = null;
        $this->hasMorePosts = false;
        $this->postsLoaded = false;
    }
};
?>

@placeholder
<div data-ui="profile-tab-panel-loading" id="profile-panel-posts" aria-busy="true">
 <x-ui.card>
 <div class="space-y-4">
 <div class="h-4 w-40 animate-pulse rounded-full bg-cream"></div>
 <div class="h-32 animate-pulse rounded-xl bg-cream"></div>
 <div class="h-32 animate-pulse rounded-xl bg-cream"></div>
 </div>
 </x-ui.card>
</div>
@endplaceholder

@php
 $data = $this->viewData();
 $mediaToggleIcon = '<path stroke-linecap="round" stroke-linejoin="round" d="M4 7.5A2.5 2.5 0 0 1 6.5 5h11A2.5 2.5 0 0 1 20 7.5v9A2.5 2.5 0 0 1 17.5 19h-11A2.5 2.5 0 0 1 4 16.5v-9Z" /><path stroke-linecap="round" stroke-linejoin="round" d="m7 15 2.25-2.25a1.2 1.2 0 0 1 1.7 0L13 14.8l1.15-1.15a1.2 1.2 0 0 1 1.7 0L18 15.8" /><path stroke-linecap="round" stroke-linejoin="round" d="M8.75 8.75h.01" />';
 $fullFeedIcon = '<path stroke-linecap="round" stroke-linejoin="round" d="M5 6.5h14M5 12h14M5 17.5h14" />';
 $closeIcon = '<path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />';
 $playIcon = '<path stroke-linecap="round" stroke-linejoin="round" d="M8 5v14l11-7L8 5Z" />';
@endphp

<div data-ui="profile-tab-panel" id="profile-panel-posts" class="space-y-4">
 @if (! $data['canViewContent'])
 <x-ui.card>
 <x-ui.empty-state icon="🔒" title="This profile is private"
 description="Follow {{ $data['profileUser']->name }} to view posts."/>
 </x-ui.card>
 @else
 <div class="flex items-center justify-end">
 <x-ui.icon-button
 type="button"
 size="sm"
 :variant="$data['mediaOnly'] ? 'primary' : 'outline'"
 :icon="$data['mediaOnly'] ? $fullFeedIcon : $mediaToggleIcon"
 :label="$data['mediaOnly'] ? 'Show full post cards' : 'Show media grid'"
 wire:click="toggleMediaOnly"
 wire:loading.attr="disabled"
 wire:target="toggleMediaOnly"
 aria-pressed="{{ $data['mediaOnly'] ? 'true' : 'false' }}"
 data-ui="profile-posts-media-toggle"
 />
 </div>

 @if (! $data['mediaOnly'])
 @if ($data['isOwner'])
 <x-ui.card>
 <div class="flex items-center gap-3">
 <x-ui.avatar :src="$data['profileUser']->avatar_url" :name="$data['profileUser']->name" size="md"/>
 <a href="{{ route('posts.create') }}"
 class="flex min-h-11 w-full items-center rounded-full border border-whisker/40 bg-cream px-4 py-2 text-left text-sm text-fur transition-colors hover:bg-paw-light/30 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
 What's on your mind, {{ $data['profileUser']->name }}?
 </a>
 </div>
 <div class="mt-3 grid grid-cols-3 gap-2">
 <a href="{{ route('posts.create') }}"
 class="flex min-h-11 items-center justify-center rounded-lg border border-whisker/30 bg-warm-white px-3 py-2 text-center text-xs font-semibold text-fur transition-colors hover:bg-cream focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">📷
 Photo</a>
 <a href="{{ route('posts.create') }}"
 class="flex min-h-11 items-center justify-center rounded-lg border border-whisker/30 bg-warm-white px-3 py-2 text-center text-xs font-semibold text-fur transition-colors hover:bg-cream focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">🐾
 Pet update</a>
 <a href="{{ route('posts.create') }}"
 class="flex min-h-11 items-center justify-center rounded-lg border border-whisker/30 bg-warm-white px-3 py-2 text-center text-xs font-semibold text-fur transition-colors hover:bg-cream focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">🎉
 Life event</a>
 </div>
 </x-ui.card>
 @endif

 @if ($data['pinnedPost'] instanceof Post)
 <section data-ui="profile-pinned-post-section" aria-label="Pinned post">
 <x-post-card :post="$data['pinnedPost']" context="profile" instance="pinned-highlight"/>
 </section>
 @endif

 @forelse ($data['posts'] as $post)
 <x-post-card :post="$post" context="profile"/>
 @empty
 <x-ui.empty-state icon="📝" title="No posts yet" description="No posts published yet."/>
 @endforelse
 @else
 @php
 $hasMediaGridItems = $data['posts']->contains(
 fn (Post $post): bool => $post->mediaItemsForDisplay()->isNotEmpty()
 );
 @endphp

 @if ($hasMediaGridItems)
 <div data-ui="profile-posts-media-grid" class="columns-1 gap-3 sm:columns-2 lg:columns-3">
 @foreach ($data['posts'] as $post)
 @foreach ($post->mediaItemsForDisplay() as $mediaItem)
 @php
 $mediaUrl = Post::mediaItemUrl($mediaItem);
 $isVideoMedia = Post::mediaItemIsVideo($mediaItem);
 $mediaAlt = __('Media from :name\'s post', ['name' => $data['profileUser']->name]);
 @endphp

 @continue($mediaUrl === '')

 <button
 type="button"
 wire:key="profile-media-grid-{{ $post->getKey() }}-{{ $loop->parent->iteration }}-{{ $loop->iteration }}"
 wire:click="openMediaPost({{ (int) $post->getKey() }})"
 class="group relative mb-3 block w-full break-inside-avoid overflow-hidden rounded-[var(--radius-soft)] border border-whisker/30 bg-cream text-left shadow-sm transition-[border-color,scale,box-shadow] duration-150 hover:border-paw/50 hover:shadow-md focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw"
 aria-label="{{ __('Open media post by :name', ['name' => $data['profileUser']->name]) }}"
 data-ui="profile-media-grid-item"
 >
 @if ($isVideoMedia)
 <video muted playsinline preload="metadata" class="max-h-96 min-h-44 w-full bg-bark object-cover">
 <source src="{{ $mediaUrl }}" type="{{ $mediaItem->mime_type ?? 'video/mp4' }}">
 </video>
 <span class="absolute inset-0 flex items-center justify-center bg-bark/10 text-white transition-colors group-hover:bg-bark/20 group-focus-visible:bg-bark/20" aria-hidden="true">
 <span class="inline-flex h-12 w-12 items-center justify-center rounded-full bg-bark/60 backdrop-blur-sm">
 <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-6 w-6">
 {!! $playIcon !!}
 </svg>
 </span>
 </span>
 @else
 <img src="{{ $mediaUrl }}" alt="{{ $mediaAlt }}" class="min-h-44 w-full object-cover" loading="lazy">
 @endif

 <span class="pointer-events-none absolute inset-x-0 bottom-0 flex translate-y-full items-center justify-between gap-2 bg-bark/70 px-3 py-2 text-xs font-semibold text-white opacity-0 backdrop-blur-sm transition-[opacity,translate] duration-150 group-hover:translate-y-0 group-hover:opacity-100 group-focus-visible:translate-y-0 group-focus-visible:opacity-100">
 <span>{{ number_format((int) ($post->likes_count ?? 0)) }} likes</span>
 <span>{{ number_format((int) ($post->comments_count ?? 0)) }} comments</span>
 </span>
 </button>
 @endforeach
 @endforeach
 </div>
 @else
 <x-ui.empty-state icon="📷" title="No media posts yet" description="Posts with photos or videos will appear here."/>
 @endif
 @endif

 @if ($data['hasMorePosts'])
 <div data-ui="profile-posts-infinite-scroll-trigger" wire:intersect.margin.400px="loadMorePosts" aria-live="polite">
 <div wire:loading.block wire:target="loadMorePosts" data-ui="profile-posts-loading-skeleton" role="status" aria-label="Loading more posts" class="space-y-4">
 @for ($index = 0; $index < 3; $index++)
 <x-ui.card>
 <div class="animate-pulse space-y-4">
 <div class="flex items-center gap-3">
 <div class="h-11 w-11 rounded-full bg-gray-200"></div>
 <div class="min-w-0 flex-1 space-y-2">
 <div class="h-4 w-32 rounded-full bg-gray-200"></div>
 <div class="h-3 w-24 rounded-full bg-gray-100"></div>
 </div>
 </div>
 <div class="space-y-2">
 <div class="h-4 w-full rounded-full bg-gray-200"></div>
 <div class="h-4 w-5/6 rounded-full bg-gray-200"></div>
 <div class="h-4 w-2/3 rounded-full bg-gray-100"></div>
 </div>
 <div class="h-28 rounded-[var(--radius-soft)] bg-gray-100"></div>
 </div>
 </x-ui.card>
 @endfor
 </div>
 <div wire:loading.remove wire:target="loadMorePosts" class="h-8" aria-hidden="true"></div>
 </div>
 @endif
 @endif

 @if ($data['selectedPost'] instanceof Post)
 <div
 data-ui="profile-media-post-modal"
 class="fixed inset-0 z-50 overflow-y-auto"
 role="dialog"
 aria-modal="true"
 aria-labelledby="profile-media-post-modal-title"
 x-data
 @keydown.escape.window="$wire.closePostModal()"
 >
 <button type="button" class="fixed inset-0 bg-bark/50" wire:click="closePostModal">
 <span class="sr-only">Close post modal</span>
 </button>

 <div class="relative mx-auto flex min-h-full w-full max-w-4xl items-start justify-center px-4 py-6 sm:items-center">
 <div class="relative w-full overflow-hidden rounded-[var(--radius-card)] bg-[color:var(--surface-modal)] shadow-2xl">
 <div class="flex items-start justify-between gap-4 border-b border-whisker/40 px-4 py-3 sm:px-6">
 <div>
 <h3 id="profile-media-post-modal-title" class="text-lg font-semibold font-display text-bark">Post</h3>
 </div>
 <x-ui.icon-button
 type="button"
 size="sm"
 variant="ghost"
 :icon="$closeIcon"
 label="Close post modal"
 wire:click="closePostModal"
 data-ui="profile-media-post-modal-close"
 />
 </div>

 <div class="max-h-[calc(100vh-7rem)] overflow-y-auto px-4 py-4 sm:px-6">
 <x-post-card :post="$data['selectedPost']" context="profile" instance="media-modal"/>

 <section class="mt-4 rounded-[var(--radius-card)] border border-whisker/40 bg-cream/30 p-4" data-ui="profile-media-post-modal-comments">
 <div class="flex items-center justify-between gap-3">
 <h4 class="text-sm font-semibold text-bark">Comments</h4>
 <span class="text-sm text-fur">{{ number_format((int) ($data['selectedPost']->comments_count ?? 0)) }}</span>
 </div>

 @forelse ($data['selectedPostComments'] as $comment)
 <x-comment-item :comment="$comment" :post="$data['selectedPost']"/>
 @empty
 <x-ui.empty-state title="No comments yet" description="Be the first to share your thoughts!" icon="💬"/>
 @endforelse
 </section>
 </div>
 </div>
 </div>
 </div>
 @endif
</div>
