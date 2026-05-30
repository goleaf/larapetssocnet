<?php

use App\Models\Content\Post;
use App\Models\Identity\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;

new class extends Component
{
    private const POSTS_PER_PAGE = 15;

    #[Url(as: 'source', except: '')]
    public string $source = '';

    #[Url(as: 'type', except: '')]
    public string $type = '';

    /**
     * @var list<int>
     */
    public array $postIds = [];

    public ?string $nextCursor = null;

    public bool $hasMorePosts = false;

    public bool $postsLoaded = false;

    public int $newPostsCount = 0;

    public ?int $newestPostId = null;

    public ?string $newestPostCreatedAt = null;

    public function mount(?string $source = null, ?string $type = null): void
    {
        $this->source = $this->sanitizeSource($source ?? $this->source);
        $this->type = $this->sanitizeType($type ?? $this->type);
    }

    public function updatedSource(): void
    {
        $this->source = $this->sanitizeSource($this->source);
        $this->resetFeed();
    }

    public function updatedType(): void
    {
        $this->type = $this->sanitizeType($this->type);
        $this->resetFeed();
    }

    public function setSource(?string $source): void
    {
        $this->source = $this->sanitizeSource($source);
        $this->resetFeed();
    }

    public function setType(?string $type): void
    {
        $this->type = $this->sanitizeType($type);
        $this->resetFeed();
    }

    public function loadMore(): void
    {
        if ($this->postsLoaded && ! $this->hasMorePosts) {
            return;
        }

        $this->appendPosts($this->postsLoaded ? $this->nextCursor : null);
    }

    public function checkForNewPosts(): void
    {
        $this->ensureLoaded();

        if ($this->newestPostId === null || $this->newestPostCreatedAt === null) {
            $this->newPostsCount = 0;

            return;
        }

        $this->newPostsCount = $this->baseFeedQuery()
            ->where($this->newerThanCurrentTop(...))
            ->count();
    }

    public function loadNewPosts(): void
    {
        $this->ensureLoaded();

        if ($this->newestPostId === null || $this->newestPostCreatedAt === null) {
            $this->newPostsCount = 0;

            return;
        }

        $newPostIds = $this->baseFeedQuery()
            ->where($this->newerThanCurrentTop(...))
            ->orderByDesc('posts.created_at')
            ->orderByDesc('posts.id')
            ->limit(50)
            ->pluck('posts.id')
            ->map(fn (mixed $postId): int => (int) $postId)
            ->all();

        if ($newPostIds !== []) {
            $this->postIds = collect([...$newPostIds, ...$this->postIds])
                ->unique()
                ->values()
                ->all();
            $this->refreshNewestPostMarker();
        }

        $this->newPostsCount = 0;
        $this->dispatch('feed-new-posts-loaded');
    }

    /**
     * @return array{
     *     posts: Collection<int, Post>,
     *     source: string,
     *     type: string,
     *     hasMorePosts: bool,
     *     newPostsCount: int
     * }
     */
    public function viewData(): array
    {
        $this->ensureLoaded();

        return [
            'posts' => Post::mainFeedPostsByIds($this->viewer(), $this->postIds, $this->normalizedType(), $this->normalizedSource()),
            'source' => $this->source,
            'type' => $this->type,
            'hasMorePosts' => $this->hasMorePosts,
            'newPostsCount' => $this->newPostsCount,
        ];
    }

    private function ensureLoaded(): void
    {
        if ($this->postsLoaded) {
            return;
        }

        $this->appendPosts();
    }

    private function appendPosts(?string $cursor = null): void
    {
        $posts = Post::paginateMainFeedResults(
            $this->viewer(),
            $this->normalizedType(),
            self::POSTS_PER_PAGE,
            $this->normalizedSource(),
            $cursor,
        );

        foreach ($posts->items() as $post) {
            $postId = (int) $post->getKey();

            if (! in_array($postId, $this->postIds, true)) {
                $this->postIds[] = $postId;
            }
        }

        $this->nextCursor = $posts->nextCursor()?->encode();
        $this->hasMorePosts = $posts->hasMorePages();
        $this->postsLoaded = true;
        $this->refreshNewestPostMarker();
    }

    private function refreshNewestPostMarker(): void
    {
        $newestPost = Post::query()
            ->select(['posts.id', 'posts.created_at'])
            ->whereIn('posts.id', $this->postIds)
            ->orderByDesc('posts.created_at')
            ->orderByDesc('posts.id')
            ->first();

        if (! $newestPost instanceof Post) {
            $this->newestPostId = null;
            $this->newestPostCreatedAt = null;

            return;
        }

        $this->newestPostId = (int) $newestPost->getKey();
        $this->newestPostCreatedAt = $newestPost->created_at?->toIso8601String();
    }

    private function newerThanCurrentTop(Builder $query): void
    {
        $createdAt = CarbonImmutable::parse((string) $this->newestPostCreatedAt);

        $query
            ->where('posts.created_at', '>', $createdAt)
            ->orWhere(function (Builder $tieQuery) use ($createdAt): void {
                $tieQuery
                    ->where('posts.created_at', $createdAt)
                    ->where('posts.id', '>', (int) $this->newestPostId);
            });
    }

    private function baseFeedQuery(): Builder
    {
        $viewer = $this->viewer();

        return Post::query()
            ->forFeed((int) $viewer->getKey())
            ->forFeedSource((int) $viewer->getKey(), $this->normalizedSource())
            ->when($this->normalizedType() !== null, fn (Builder $query) => $query->byType((string) $this->normalizedType()));
    }

    private function resetFeed(): void
    {
        $this->postIds = [];
        $this->nextCursor = null;
        $this->hasMorePosts = false;
        $this->postsLoaded = false;
        $this->newPostsCount = 0;
        $this->newestPostId = null;
        $this->newestPostCreatedAt = null;
    }

    private function viewer(): User
    {
        $viewer = auth()->user();

        abort_unless($viewer instanceof User, 403);

        return $viewer;
    }

    private function normalizedSource(): ?string
    {
        return $this->source !== '' ? $this->source : null;
    }

    private function normalizedType(): ?string
    {
        return $this->type !== '' ? $this->type : null;
    }

    private function sanitizeSource(?string $source): string
    {
        return in_array($source, ['people', 'pets'], true) ? (string) $source : '';
    }

    private function sanitizeType(?string $type): string
    {
        return in_array($type, ['text', 'photo', 'video'], true) ? (string) $type : '';
    }
};
?>

@php
    $data = $this->viewData();

    $sourceFilters = [
        ['value' => '', 'label' => __('feed.filters.all')],
        ['value' => 'people', 'label' => __('feed.filters.people')],
        ['value' => 'pets', 'label' => __('feed.filters.pets')],
    ];

    $typeFilters = [
        ['value' => '', 'label' => __('feed.filters.all_types')],
        ['value' => 'photo', 'label' => __('feed.filters.photos')],
        ['value' => 'video', 'label' => __('feed.filters.videos')],
        ['value' => 'text', 'label' => __('feed.filters.text')],
    ];
@endphp

<section
    id="feed-stream-top"
    data-ui="feed-stream"
    class="space-y-4"
    x-data="feedLiveState()"
    x-init="start($wire)"
    x-on:feed-new-posts-loaded.window="$nextTick(() => document.getElementById('feed-stream-top')?.scrollIntoView({ behavior: 'smooth', block: 'start' }))"
>
    <x-ui.card padding="base" data-ui="feed-moments-strip">
        <div class="flex items-center justify-between gap-3">
            <div>
                <p class="shell-kicker">{{ __('feed.moments_title') }}</p>
                <h2 class="mt-1 text-lg font-bold font-display text-bark">{{ __('feed.moments_heading') }}</h2>
            </div>
            <x-ui.button href="{{ route('posts.create') }}" variant="ghost" size="sm">{{ __('feed.moments_add') }}</x-ui.button>
        </div>
        <div class="mt-4 flex snap-x gap-3 overflow-x-auto pb-1">
            <a href="{{ route('posts.create') }}" class="snap-start rounded-[var(--radius-soft)] text-center focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
                <span class="mx-auto flex h-16 w-16 items-center justify-center rounded-pill border-2 border-dashed border-paw-light bg-paw-light/30 text-xl font-bold text-paw">+</span>
                <span class="mt-2 block max-w-20 truncate text-xs font-semibold text-bark">{{ __('feed.moments_your_story') }}</span>
            </a>
            @foreach ($data['posts']->pluck('user')->filter()->unique('id')->take(10) as $momentUser)
                <a href="{{ route('profile.show', $momentUser) }}" class="snap-start rounded-[var(--radius-soft)] text-center focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
                    <span class="mx-auto inline-flex rounded-pill bg-gradient-to-br from-paw-light via-cream to-leaf-light p-0.5">
                        <x-ui.avatar :src="$momentUser->avatar_url" :name="$momentUser->name" :user="$momentUser" size="lg"/>
                    </span>
                    <span class="mt-2 block max-w-20 truncate text-xs font-semibold text-bark">{{ $momentUser->name }}</span>
                </a>
            @endforeach
        </div>
    </x-ui.card>

    <x-ui.card padding="base" data-ui="feed-filter-bar">
        <div class="grid gap-4 lg:grid-cols-[12rem_minmax(0,1fr)] lg:items-center">
            <div>
                <p class="shell-kicker">{{ __('feed.filters_title') }}</p>
                <h2 class="mt-1 text-lg font-bold font-display text-bark">{{ __('feed.filters_heading') }}</h2>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center lg:justify-end">
                <div class="flex flex-wrap gap-2" aria-label="{{ __('feed.filters_source_label') }}">
                    @foreach ($sourceFilters as $filter)
                        <button
                            type="button"
                            wire:click="setSource('{{ $filter['value'] }}')"
                            @class([
                                'inline-flex min-h-10 items-center rounded-[var(--radius-soft)] border px-3 text-sm font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw',
                                'border-paw-light bg-paw-light text-paw-dark' => $data['source'] === $filter['value'],
                                'border-whisker/40 bg-transparent text-fur hover:bg-cream hover:text-bark' => $data['source'] !== $filter['value'],
                            ])
                            @if ($data['source'] === $filter['value']) aria-current="true" @endif
                        >
                            {{ $filter['label'] }}
                        </button>
                    @endforeach
                </div>

                <div class="flex flex-wrap gap-2" aria-label="{{ __('feed.filters_type_label') }}">
                    @foreach ($typeFilters as $filter)
                        <button
                            type="button"
                            wire:click="setType('{{ $filter['value'] }}')"
                            @class([
                                'inline-flex min-h-10 items-center rounded-[var(--radius-soft)] border px-3 text-sm font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw',
                                'border-paw-light bg-paw-light text-paw-dark' => $data['type'] === $filter['value'],
                                'border-whisker/40 bg-transparent text-fur hover:bg-cream hover:text-bark' => $data['type'] !== $filter['value'],
                            ])
                            @if ($data['type'] === $filter['value']) aria-current="true" @endif
                        >
                            {{ $filter['label'] }}
                        </button>
                    @endforeach
                </div>
            </div>
        </div>
    </x-ui.card>

    <livewire:posts.composer mode="inline" context-type="feed" />

    <x-ui.card padding="base" role="status">
        <p class="flex items-start gap-2 text-sm leading-6 text-fur">
            <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-paw" aria-hidden="true"></span>
            <span>{{ __('feed.feed_note') }}</span>
        </p>
    </x-ui.card>

    @if ($data['newPostsCount'] > 0)
        <div class="sticky top-20 z-20 flex justify-center" aria-live="polite" data-ui="feed-new-posts-indicator">
            <button
                type="button"
                wire:click="loadNewPosts"
                class="inline-flex min-h-10 items-center rounded-pill border border-paw-light bg-paw px-4 text-sm font-bold text-white shadow-card transition hover:bg-paw-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw"
            >
                {{ trans_choice('feed.new_posts_indicator', $data['newPostsCount'], ['count' => $data['newPostsCount']]) }}
            </button>
        </div>
    @endif

    <ul role="feed" aria-label="{{ __('feed.aria_feed') }}" class="space-y-4" x-data="feedPostList()" x-on:post-created.window="prependPost($event)">
        <template x-for="post in pendingPosts" :key="`pending-${post.id}`">
            <li
                x-transition.opacity.scale.95.duration.300ms
                class="shell-card p-5 transition duration-700"
                x-bind:class="{ 'bg-paw/5 ring-2 ring-paw/30': post.highlighted }"
                x-bind:aria-label="`Post by ${post.authorName}`"
            >
                <article class="space-y-4">
                    <div class="flex items-center gap-3">
                        <template x-if="post.authorAvatar">
                            <img x-bind:src="post.authorAvatar" alt="" class="h-11 w-11 rounded-full border border-whisker/30 object-cover">
                        </template>
                        <template x-if="!post.authorAvatar">
                            <span class="inline-flex h-11 w-11 items-center justify-center rounded-full border border-whisker/30 bg-cream text-sm font-bold text-paw" x-text="post.authorName.slice(0, 1).toUpperCase()"></span>
                        </template>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-bold text-bark" x-text="post.authorName"></p>
                            <p class="text-xs text-fur" x-text="post.createdAt"></p>
                        </div>
                        <span class="ms-auto inline-flex rounded-full bg-paw/10 px-2.5 py-1 text-xs font-bold text-paw-dark">{{ __('feed.pending_post_badge') }}</span>
                    </div>
                    <p class="whitespace-pre-line text-sm leading-6 text-bark" x-text="post.body"></p>
                </article>
            </li>
        </template>

        @forelse ($data['posts'] as $post)
            <li wire:key="feed-post-{{ $post->getKey() }}" aria-label="{{ __('Post by :name', ['name' => $post->author?->name ?? __('a community member')]) }}">
                <x-post-card :post="$post" />
            </li>
        @empty
            <li>
                <x-ui.empty-state :title="__('feed.empty_title')" :description="__('feed.empty_description')">
                    <x-slot:action>
                        <div class="flex flex-wrap justify-center gap-2">
                            <x-ui.button href="{{ route('search.index', ['type' => 'users']) }}" variant="secondary">{{ __('feed.empty_find_people') }}</x-ui.button>
                            <x-ui.button href="{{ route('explore.index', ['tab' => 'pets']) }}" variant="primary">{{ __('feed.empty_browse_pets') }}</x-ui.button>
                        </div>
                    </x-slot:action>
                </x-ui.empty-state>
            </li>
        @endforelse
    </ul>

    @if ($data['hasMorePosts'])
        <div data-ui="feed-infinite-scroll-trigger" wire:intersect.margin.300px="loadMore" aria-live="polite">
            <div wire:loading.block wire:target="loadMore" role="status" class="flex min-h-16 items-center justify-center">
                <span class="inline-flex items-center gap-2 text-sm font-semibold text-fur">
                    <svg class="h-4 w-4 animate-spin text-paw" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z"></path>
                    </svg>
                    {{ __('feed.loading_more') }}
                </span>
            </div>
            <div wire:loading.remove wire:target="loadMore" class="h-8" aria-hidden="true"></div>
        </div>
    @elseif ($data['posts']->isNotEmpty())
        <x-ui.card padding="base" data-ui="feed-end-state">
            <p class="text-center text-sm leading-6 text-fur">
                {{ __('feed.end_message') }}
                <a href="{{ route('explore.index') }}" class="font-semibold text-paw hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">{{ __('feed.end_action') }}</a>
            </p>
        </x-ui.card>
    @endif
</section>
