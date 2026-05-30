<div class="space-y-4" @if ($isMounted) wire:poll.45s.keep-alive="pollForNewComments" @endif>
    @if (! $isMounted)
        <button type="button" wire:click="openSection" class="inline-flex items-center gap-2 rounded-full border border-fur/20 bg-warm-white px-4 py-2 text-sm font-semibold text-bark shadow-sm transition hover:border-paw/40 hover:text-paw focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
            <span>Comments</span>
            <span class="rounded-full bg-paw-light px-2 py-0.5 text-xs text-paw-dark">{{ $totalCommentCount }}</span>
        </button>
    @endif

    @if ($isMounted)
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="inline-flex w-fit overflow-hidden rounded-full border border-fur/15 bg-cream p-1 text-xs font-semibold text-fur">
                <button type="button" wire:click="switchSort('newest')" class="rounded-full px-3 py-1.5 transition @if ($sortMode === 'newest') bg-warm-white text-bark shadow-sm @else hover:text-bark @endif">Newest</button>
                <button type="button" wire:click="switchSort('oldest')" class="rounded-full px-3 py-1.5 transition @if ($sortMode === 'oldest') bg-warm-white text-bark shadow-sm @else hover:text-bark @endif">Oldest</button>
                <button type="button" wire:click="switchSort('top')" class="rounded-full px-3 py-1.5 transition @if ($sortMode === 'top') bg-warm-white text-bark shadow-sm @else hover:text-bark @endif">Top</button>
            </div>

            @if ($this->viewerCanPin)
                <button type="button" wire:click="$dispatch('open-comment-insights', { postId: {{ $postId }} })" class="text-xs font-semibold text-fur transition hover:text-paw">Insights</button>
            @endif
        </div>

        @if ($this->showSearchInput)
            <label class="block">
                <span class="sr-only">Search comments</span>
                <input type="search" wire:model.live.debounce.350ms="searchQuery" placeholder="Search comments" class="w-full rounded-full border border-fur/20 bg-warm-white px-4 py-2 text-sm text-bark placeholder:text-fur/70 focus:border-paw focus:outline-none focus:ring-2 focus:ring-paw/20">
            </label>
        @endif

        @if ($showNewCommentIndicator)
            <button type="button" wire:click="loadNewComments" x-data="{ pulse: false }" x-init="setInterval(() => { pulse = ! pulse }, 1600)" :class="{ 'animate-pulse': pulse }" class="w-full rounded-full bg-paw px-4 py-2 text-center text-sm font-bold text-white shadow-sm">
                @if ($newCommentCount === 1)
                    1 new comment
                @else
                    {{ $newCommentCount }} new comments
                @endif
            </button>
        @endif

        @if ($pinnedComment)
            <livewire:comments.comment-card :comment-id="$pinnedComment['id']" :post-id="$postId" :is-pinned="true" :viewer-can-pin="$this->viewerCanPin" :wire:key="'pinned-comment-'.$pinnedComment['id']" />
        @endif

        <div class="space-y-4">
            @forelse ($comments as $comment)
                <livewire:comments.comment-card :comment-id="$comment['id']" :post-id="$postId" :viewer-can-pin="$this->viewerCanPin" :wire:key="'comment-card-'.$comment['id'].'-'.$sortMode" />
            @empty
                <div class="rounded-[var(--radius-soft)] border border-dashed border-fur/20 bg-cream/60 px-4 py-6 text-center text-sm text-fur">
                    No comments yet.
                </div>
            @endforelse
        </div>

        @if (! $noMoreComments && ! $isSearchMode)
            <div wire:intersect.margin.400px="loadMore" class="py-2">
                @if ($isLoadingMore)
                    <div class="space-y-3">
                        <div class="h-16 animate-pulse rounded-[var(--radius-soft)] bg-cream"></div>
                        <div class="h-16 animate-pulse rounded-[var(--radius-soft)] bg-cream"></div>
                        <div class="h-16 animate-pulse rounded-[var(--radius-soft)] bg-cream"></div>
                    </div>
                @endif
            </div>
        @elseif ($noMoreComments && count($comments) > 0 && ! $isSearchMode)
            <p class="text-center text-xs text-fur">All comments loaded</p>
        @endif

        @if ($this->isAuthenticated)
            <livewire:comments.top-level-comment-composer :post-id="$postId" :wire:key="'top-level-comment-composer-'.$postId" />
        @else
            <div class="rounded-[var(--radius-soft)] border border-fur/15 bg-cream/60 px-4 py-3 text-sm text-fur">
                Log in to join the conversation.
            </div>
        @endif

        <livewire:comments.comment-insights-modal :post-id="$postId" :wire:key="'comment-insights-'.$postId" />
    @endif

    <div wire:loading.class.remove="hidden" class="hidden rounded-full bg-bark/80 px-3 py-1 text-xs font-semibold text-white">
        Updating comments...
    </div>
</div>
