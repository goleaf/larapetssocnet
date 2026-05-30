<?php

use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Services\FeedService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;

new class extends Component
{
    private const POSTS_PER_PAGE = 15;

    private const SESSION_LAST_SEEN_POST_ID = 'feed.last_seen_post_id';

    private const SESSION_LAST_SEEN_SOURCE = 'feed.last_seen_source';

    private const SESSION_LAST_SEEN_TYPE = 'feed.last_seen_type';

    private const SESSION_LAST_SEEN_RANKING = 'feed.last_seen_ranking';

    #[Url(as: 'source', except: '')]
    public string $source = '';

    #[Url(as: 'type', except: '')]
    public string $type = '';

    #[Url(as: 'rank', except: 'latest')]
    public string $ranking = 'latest';

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

    public ?int $polledNewestPostId = null;

    public function mount(?string $source = null, ?string $type = null): void
    {
        $this->source = $this->sanitizeSource($source ?? $this->source);
        $this->type = $this->sanitizeType($type ?? $this->type);
        $requestedRanking = request()->query('rank');
        $this->ranking = $this->sanitizeRanking(
            is_string($requestedRanking) ? $requestedRanking : $this->viewer()->preferredFeedRanking()
        );
        $this->restoreReadPositionIfPossible();
    }

    public ?int $restoreFromPostId = null;

    public bool $restoredReadPosition = false;

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

    public function updatedRanking(): void
    {
        $this->setRanking($this->ranking);
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

    public function setRanking(string $ranking): void
    {
        $this->ranking = $this->sanitizeRanking($ranking);
        $this->viewer()
            ->forceFill(['feed_ranking_preference' => $this->ranking])
            ->save();
        $this->forgetReadPosition();
        $this->resetFeed();
    }

    public function loadMore(): void
    {
        if ($this->postsLoaded && ! $this->hasMorePosts) {
            return;
        }

        $this->appendPosts($this->postsLoaded ? $this->nextCursor : null);
        $this->rememberReadPosition();
    }

    public function checkForNewPosts(): void
    {
        $this->ensureLoaded();

        if ($this->newestPostId === null || $this->newestPostCreatedAt === null) {
            $this->newPostsCount = 0;

            return;
        }

        $newerPostsQuery = $this->baseFeedQuery()
            ->where($this->newerThanCurrentTop(...));

        $this->newPostsCount = (int) (clone $newerPostsQuery)->count();

        $polledNewestPost = $newerPostsQuery
            ->select(['posts.id', 'posts.created_at'])
            ->orderByDesc('posts.created_at')
            ->orderByDesc('posts.id')
            ->first();

        $this->polledNewestPostId = $polledNewestPost instanceof Post
            ? (int) $polledNewestPost->getKey()
            : null;
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
        $this->polledNewestPostId = null;
        $this->dispatch('feed-new-posts-loaded');
    }

    public function jumpToLatest(): void
    {
        $this->forgetReadPosition();
        $this->resetFeed();
        $this->dispatch('feed-new-posts-loaded');
    }

    /**
     * @return array{
     *     posts: Collection<int, Post>,
     *     source: string,
     *     type: string,
     *     hasMorePosts: bool,
     *     newPostsCount: int,
     *     ranking: string,
     *     feedHealth: string,
     *     feedHealthLabel: string,
     *     restoredReadPosition: bool,
     *     emptySuggestions: Collection<int, User>
     * }
     */
    public function viewData(): array
    {
        $this->ensureLoaded();
        $posts = Post::mainFeedPostsByIds($this->viewer(), $this->postIds, $this->normalizedType(), $this->normalizedSource());
        $feedHealth = $this->feedHealthStatus();

        return [
            'posts' => $posts,
            'source' => $this->source,
            'type' => $this->type,
            'hasMorePosts' => $this->hasMorePosts,
            'newPostsCount' => $this->newPostsCount,
            'ranking' => $this->ranking,
            'feedHealth' => $feedHealth,
            'feedHealthLabel' => $this->feedHealthLabel($feedHealth),
            'restoredReadPosition' => $this->restoredReadPosition,
            'emptySuggestions' => $posts->isEmpty()
                ? app(FeedService::class)->contextualEmptyFeedSuggestions($this->viewer())
                : collect(),
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
            $this->ranking,
            $cursor === null ? $this->restoreFromPostId : null,
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
        $this->restoreFromPostId = null;
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
            ->forFeed((int) $viewer->getKey(), $this->normalizedSource())
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
        $this->polledNewestPostId = null;
        $this->restoreFromPostId = null;
        $this->restoredReadPosition = false;
    }

    private function rememberReadPosition(): void
    {
        $lastSeenPostId = collect($this->postIds)->last();

        if (! is_int($lastSeenPostId) || $lastSeenPostId <= 0) {
            return;
        }

        session([
            self::SESSION_LAST_SEEN_POST_ID => $lastSeenPostId,
            self::SESSION_LAST_SEEN_SOURCE => $this->source,
            self::SESSION_LAST_SEEN_TYPE => $this->type,
            self::SESSION_LAST_SEEN_RANKING => $this->ranking,
        ]);
    }

    private function restoreReadPositionIfPossible(): void
    {
        $lastSeenPostId = (int) session(self::SESSION_LAST_SEEN_POST_ID, 0);

        if ($lastSeenPostId <= 0) {
            return;
        }

        if (
            session(self::SESSION_LAST_SEEN_SOURCE, '') !== $this->source
            || session(self::SESSION_LAST_SEEN_TYPE, '') !== $this->type
            || session(self::SESSION_LAST_SEEN_RANKING, User::FEED_RANKING_LATEST) !== $this->ranking
        ) {
            return;
        }

        $this->restoreFromPostId = $lastSeenPostId;
        $this->restoredReadPosition = true;
    }

    private function forgetReadPosition(): void
    {
        session()->forget([
            self::SESSION_LAST_SEEN_POST_ID,
            self::SESSION_LAST_SEEN_SOURCE,
            self::SESSION_LAST_SEEN_TYPE,
            self::SESSION_LAST_SEEN_RANKING,
        ]);

        $this->restoreFromPostId = null;
        $this->restoredReadPosition = false;
    }

    private function feedHealthStatus(): string
    {
        $latestCreatedAt = $this->latestFeedPostCreatedAt();

        if ($latestCreatedAt === null) {
            return 'grey';
        }

        $ageInHours = $latestCreatedAt->diffInHours(now());

        if ($ageInHours <= 1) {
            return 'green';
        }

        if ($ageInHours <= 24) {
            return 'yellow';
        }

        return 'grey';
    }

    private function feedHealthLabel(string $status): string
    {
        return match ($status) {
            'green' => __('feed.ranking.health_green'),
            'yellow' => __('feed.ranking.health_yellow'),
            default => __('feed.ranking.health_grey'),
        };
    }

    private function latestFeedPostCreatedAt(): ?CarbonImmutable
    {
        $latestPost = $this->baseFeedQuery()
            ->select(['posts.id', 'posts.created_at'])
            ->orderByDesc('posts.created_at')
            ->orderByDesc('posts.id')
            ->first();

        if (! $latestPost instanceof Post || $latestPost->created_at === null) {
            return null;
        }

        return CarbonImmutable::parse($latestPost->created_at);
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

    private function sanitizeRanking(?string $ranking): string
    {
        return in_array($ranking, User::feedRankingPreferences(), true)
            ? (string) $ranking
            : User::FEED_RANKING_LATEST;
    }
};
?>

@placeholder
    <section data-ui="feed-stream-skeleton" class="space-y-4">
        @for ($index = 0; $index < 5; $index++)
            <x-ui.card padding="base" class="animate-pulse">
                <div class="flex items-center gap-3">
                    <div class="h-11 w-11 rounded-full bg-whisker/30"></div>
                    <div class="min-w-0 flex-1 space-y-2">
                        <div class="h-4 w-40 rounded-full bg-whisker/30"></div>
                        <div class="h-3 w-24 rounded-full bg-whisker/20"></div>
                    </div>
                    <div class="h-8 w-20 rounded-[var(--radius-soft)] bg-whisker/20"></div>
                </div>
                <div class="mt-5 space-y-3">
                    <div class="h-4 w-full rounded-full bg-whisker/20"></div>
                    <div class="h-4 w-11/12 rounded-full bg-whisker/20"></div>
                    <div class="h-4 w-2/3 rounded-full bg-whisker/20"></div>
                </div>
                <div class="mt-5 h-44 rounded-[var(--radius-soft)] bg-whisker/20"></div>
                <div class="mt-5 flex gap-2">
                    <div class="h-9 w-24 rounded-[var(--radius-soft)] bg-whisker/20"></div>
                    <div class="h-9 w-24 rounded-[var(--radius-soft)] bg-whisker/20"></div>
                    <div class="h-9 w-24 rounded-[var(--radius-soft)] bg-whisker/20"></div>
                </div>
            </x-ui.card>
        @endfor
    </section>
@endplaceholder

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

    $rankingFilters = [
        ['value' => 'latest', 'label' => __('feed.ranking.latest')],
        ['value' => 'best', 'label' => __('feed.ranking.best')],
    ];

    $feedHealthClass = match ($data['feedHealth']) {
        'green' => 'bg-emerald-500',
        'yellow' => 'bg-amber-500',
        default => 'bg-whisker',
    };
@endphp

<section
    id="feed-stream-top"
    data-ui="feed-stream"
    class="space-y-4"
    x-data="feedLiveState()"
    x-init="start($wire, $el)"
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

                <div class="flex flex-wrap gap-2" aria-label="{{ __('feed.ranking.label') }}">
                    @foreach ($rankingFilters as $filter)
                        <button
                            type="button"
                            wire:click="setRanking('{{ $filter['value'] }}')"
                            @class([
                                'inline-flex min-h-10 items-center gap-2 rounded-[var(--radius-soft)] border px-3 text-sm font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw',
                                'border-paw-light bg-paw-light text-paw-dark' => $data['ranking'] === $filter['value'],
                                'border-whisker/40 bg-transparent text-fur hover:bg-cream hover:text-bark' => $data['ranking'] !== $filter['value'],
                            ])
                            @if ($data['ranking'] === $filter['value']) aria-current="true" @endif
                        >
                            @if ($filter['value'] === 'latest')
                                <span class="h-2.5 w-2.5 rounded-full {{ $feedHealthClass }}" title="{{ $data['feedHealthLabel'] }}" aria-label="{{ $data['feedHealthLabel'] }}"></span>
                            @endif
                            <span>{{ $filter['label'] }}</span>
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

    @if ($data['restoredReadPosition'])
        <div class="sticky top-20 z-20 flex justify-center" aria-live="polite" data-ui="feed-read-position-indicator">
            <button
                type="button"
                wire:click="jumpToLatest"
                class="inline-flex min-h-10 items-center rounded-pill border border-whisker/40 bg-warm-white px-4 text-sm font-bold text-paw shadow-card transition hover:border-paw hover:bg-paw-light focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw"
            >
                {{ __('feed.jump_to_latest') }}
            </button>
        </div>
    @endif

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
                <livewire:posts.card
                    :post="$post"
                    :viewer-id="auth()->id()"
                    context="feed"
                    :instance="'feed-'.$post->getKey()"
                    :key="'feed-post-card-'.$post->getKey()"
                />
            </li>
        @empty
            <li>
                <x-ui.empty-state :title="__('feed.empty_title')" :description="__('feed.empty_description')">
                    <x-slot:action>
                        <div class="space-y-4">
                            @if ($data['emptySuggestions']->isNotEmpty())
                                <div class="mx-auto max-w-2xl rounded-[var(--radius-soft)] border border-whisker/30 bg-cream/40 p-4 text-left">
                                    <p class="text-sm font-semibold text-bark">{{ __('feed.empty_contextual_title') }}</p>
                                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                        @foreach ($data['emptySuggestions'] as $suggestedUser)
                                            <a href="{{ route('profile.show', $suggestedUser) }}" class="ui-list-item flex items-center gap-3 px-3 py-2">
                                                <x-ui.avatar :src="$suggestedUser->avatar_url" :name="$suggestedUser->name" :user="$suggestedUser" size="sm"/>
                                                <span class="min-w-0">
                                                    <span class="block truncate text-sm font-semibold text-bark">{{ $suggestedUser->name }}</span>
                                                    <span class="block truncate text-xs text-fur">&#64;{{ $suggestedUser->username }}</span>
                                                </span>
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                            <div class="flex flex-wrap justify-center gap-2">
                                <x-ui.button href="{{ route('search.index', ['type' => 'users']) }}" variant="secondary">{{ __('feed.empty_find_people') }}</x-ui.button>
                                <x-ui.button href="{{ route('explore.index', ['tab' => 'pets']) }}" variant="primary">{{ __('feed.empty_browse_pets') }}</x-ui.button>
                            </div>
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
