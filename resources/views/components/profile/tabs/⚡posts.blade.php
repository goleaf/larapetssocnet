<?php

use App\Models\Content\Post;
use App\Models\Identity\User;
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

    /**
     * @return array{
     *     profileUser: User,
     *     isOwner: bool,
     *     canViewContent: bool,
     *     pinnedPost: ?Post,
     *     posts: Collection<int, Post>,
     *     hasMorePosts: bool
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
            $this->resetTimeline();
        }

        $posts = $canViewContent
            ? Post::profileTimelinePostsByIds($profileUser, $viewer, $this->postIds)
            : collect();
        $pinnedPost = $canViewContent
            ? Post::pinnedProfileTimelinePost($profileUser, $viewer)
            : null;

        return [
            'profileUser' => $profileUser,
            'isOwner' => $isOwner,
            'canViewContent' => $canViewContent,
            'pinnedPost' => $pinnedPost,
            'posts' => $posts,
            'hasMorePosts' => $this->hasMorePosts,
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
        $posts = Post::paginateProfileTimeline($profileUser, $viewer, self::POSTS_PER_PAGE, $cursor);

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
@endphp

<div data-ui="profile-tab-panel" id="profile-panel-posts" class="space-y-4">
 @if (! $data['canViewContent'])
 <x-ui.card>
 <x-ui.empty-state icon="🔒" title="This profile is private"
 description="Follow {{ $data['profileUser']->name }} to view posts."/>
 </x-ui.card>
 @else
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

 @if ($data['isOwner'])
 <div class="-mt-2 flex items-center justify-end gap-2">
 @if ($post->is_pinned)
 <form method="POST" action="{{ route('posts.unpin', $post) }}">
 @csrf
 @method('DELETE')
 <x-ui.button type="submit" variant="ghost" size="xs" class="min-h-9">Unpin</x-ui.button>
 </form>
 @else
 <form method="POST" action="{{ route('posts.pin', $post) }}">
 @csrf
 <x-ui.button type="submit" variant="secondary" size="xs" class="min-h-9">Pin to Profile</x-ui.button>
 </form>
 @endif
 </div>
 @endif
 @empty
 <x-ui.empty-state icon="📝" title="No posts yet" description="No posts published yet."/>
 @endforelse

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
</div>
