<?php

use App\Models\Content\Post;
use App\Models\Content\Reaction;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    #[Locked]
    public int $postId;

    public bool $open = false;

    public string $filter = 'all';

    public int $page = 1;

    public int $perPage = 20;

    /**
     * @var array<string, int>
     */
    public array $summary = [];

    /**
     * @var list<array{type: string, label: string, emoji: string, count: int, icon_class: string}>
     */
    public array $top = [];

    public int $total = 0;

    public string $summaryHtml = '';

    /**
     * @param  array<string, int>  $summary
     * @param  list<array{type: string, label: string, emoji: string, count: int, icon_class: string}>  $top
     */
    public function mount(Post $post, array $summary = [], array $top = [], int $total = 0, string $summaryHtml = ''): void
    {
        $this->postId = (int) $post->getKey();
        $this->summary = $summary;
        $this->top = $top;
        $this->total = $total;
        $this->summaryHtml = $summaryHtml;
    }

    public function open(string $filter = 'all'): void
    {
        $this->refreshSummary();
        $this->setFilter($filter);
        $this->open = true;
    }

    public function close(): void
    {
        $this->open = false;
    }

    public function setFilter(string $filter): void
    {
        $normalized = $filter === 'all' ? 'all' : Reaction::normalizeType($filter);

        if ($normalized !== 'all' && ! in_array($normalized, Reaction::types(), true)) {
            $normalized = 'all';
        }

        $this->filter = $normalized;
        $this->page = 1;
    }

    public function loadMore(): void
    {
        $this->page++;
    }

    /**
     * @return list<array{type: string, label: string, emoji: string, count: int}>
     */
    public function tabs(): array
    {
        $tabs = [[
            'type' => 'all',
            'label' => 'All',
            'emoji' => '',
            'count' => $this->total,
        ]];

        foreach (Reaction::options() as $option) {
            $count = (int) ($this->summary[$option['type']] ?? 0);

            if ($count < 1) {
                continue;
            }

            $tabs[] = [
                'type' => (string) $option['type'],
                'label' => (string) $option['label'],
                'emoji' => (string) $option['emoji'],
                'count' => $count,
            ];
        }

        return $tabs;
    }

    /**
     * @return Collection<int, array{reaction: Reaction, user: User, follow_status: string}>
     */
    public function reactorRows(): Collection
    {
        $reactions = $this->reactionQuery()
            ->with('user')
            ->latest('id')
            ->limit($this->page * $this->perPage)
            ->get();

        $users = $reactions
            ->pluck('user')
            ->filter(fn ($user): bool => $user instanceof User)
            ->values();

        $followStatuses = $this->followStatusesFor($users);

        return $reactions
            ->filter(fn (Reaction $reaction): bool => $reaction->user instanceof User)
            ->map(fn (Reaction $reaction): array => [
                'reaction' => $reaction,
                'user' => $reaction->user,
                'follow_status' => $followStatuses[$reaction->user->getKey()] ?? 'none',
            ])
            ->values();
    }

    public function hasMore(): bool
    {
        return $this->reactionQuery()->count() > ($this->page * $this->perPage);
    }

    private function refreshSummary(): void
    {
        $post = Post::query()
            ->whereKey($this->postId)
            ->firstOrFail();

        $this->summary = Reaction::countMapForModel($post);
        $this->top = Reaction::topCountsForModel($post, 3);
        $this->total = (int) ($post->reactions_count ?? 0);
        $this->summaryHtml = (string) app(\App\Services\ReactionSummaryCache::class)->html($post);
    }

    private function reactionQuery(): Builder
    {
        return Reaction::query()
            ->where('reactable_type', (new Post)->getMorphClass())
            ->where('reactable_id', $this->postId)
            ->when($this->filter !== 'all', fn (Builder $query): Builder => $query->where('type', $this->filter));
    }

    /**
     * @param  Collection<int, User>  $users
     * @return array<int, string>
     */
    private function followStatusesFor(Collection $users): array
    {
        $viewer = auth()->user();

        if (! $viewer instanceof User || $users->isEmpty()) {
            return [];
        }

        $ids = $users
            ->pluck('id')
            ->reject(fn (int $id): bool => $id === (int) $viewer->getKey())
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $following = $viewer->acceptedFollowing()
            ->whereIn('users.id', $ids)
            ->pluck('users.id')
            ->mapWithKeys(fn (int $id): array => [$id => 'following']);

        $pending = $viewer->sentPendingRequests()
            ->whereIn('users.id', $ids)
            ->pluck('users.id')
            ->mapWithKeys(fn (int $id): array => [$id => 'pending']);

        return $pending
            ->merge($following)
            ->all();
    }
};
?>

<div class="contents">
    <button
        type="button"
        wire:click="open('all')"
        class="hidden min-h-11 items-center gap-2 rounded-[var(--radius-pill)] border border-transparent px-2 text-xs font-semibold text-fur transition hover:border-whisker/50 hover:bg-cream focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw sm:inline-flex"
        data-ui="post-card-reactions-trigger"
        title="{{ collect($top)->map(fn (array $reaction): string => $reaction['emoji'].' '.$reaction['count'])->implode(' · ') }}"
    >
        @if ($top !== [])
            <span class="inline-flex items-center" data-ui="post-card-reaction-breakdown">
                {!! $summaryHtml !!}
            </span>
        @endif
        <span>{{ number_format($total) }}</span>
        <span class="sr-only">View reactions</span>
    </button>

    @if ($open)
        <div
            class="fixed inset-0 z-[70] overflow-y-auto px-4 py-6 sm:px-6"
            role="dialog"
            aria-modal="true"
            aria-labelledby="post-reactions-title-{{ $this->getId() }}"
            wire:keydown.escape="close"
            data-ui="post-reactions-modal"
        >
            <div class="fixed inset-0 bg-bark/35 backdrop-blur-sm" aria-hidden="true" wire:click="close"></div>

            <div class="relative mx-auto flex min-h-full max-w-lg items-center justify-center">
                <div class="w-full overflow-hidden rounded-[var(--radius-panel)] border ui-border bg-white shadow-xl">
                    <div class="flex items-start justify-between gap-4 border-b ui-border px-6 py-5">
                        <div class="min-w-0">
                            <p id="post-reactions-title-{{ $this->getId() }}" class="text-lg font-semibold ui-text">Reactions</p>
                            <p class="mt-1 text-sm leading-6 shell-text-muted">{{ number_format($total) }} total reactions on this post.</p>
                        </div>
                        <x-ui.icon-button
                            type="button"
                            size="sm"
                            variant="ghost"
                            label="Close reactions"
                            aria-label="Close reactions"
                            wire:click="close"
                            :icon="'<path stroke-linecap=&quot;round&quot; stroke-linejoin=&quot;round&quot; d=&quot;M6 18 18 6M6 6l12 12&quot; />'"
                        />
                    </div>

                    <div class="border-b ui-border px-6 py-3">
                        <div class="flex gap-2 overflow-x-auto" role="tablist" aria-label="Reaction filters">
                            @foreach ($this->tabs() as $tab)
                                <button
                                    type="button"
                                    class="inline-flex min-h-9 shrink-0 items-center gap-1 rounded-[var(--radius-pill)] border px-3 text-xs font-semibold transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw"
                                    @class([
                                        'border-paw/40 bg-paw-light/70 text-paw' => $filter === $tab['type'],
                                        'border-whisker/40 text-fur hover:bg-cream' => $filter !== $tab['type'],
                                    ])
                                    wire:click="setFilter('{{ $tab['type'] }}')"
                                    role="tab"
                                    aria-selected="{{ $filter === $tab['type'] ? 'true' : 'false' }}"
                                    data-ui="post-reactions-tab-{{ $tab['type'] }}"
                                >
                                    @if ($tab['emoji'] !== '')
                                        <span aria-hidden="true">{{ $tab['emoji'] }}</span>
                                    @endif
                                    <span>{{ $tab['label'] }}</span>
                                    <span>{{ number_format($tab['count']) }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <div class="max-h-[60vh] overflow-y-auto px-6 py-3" data-ui="post-reactions-list">
                        @forelse ($this->reactorRows() as $row)
                            @php
                                $reactor = $row['user'];
                                $reaction = $row['reaction'];
                                $reactionType = \App\Models\Content\Reaction::normalizeType((string) $reaction->type);
                                $reactionEmoji = \App\Models\Content\Reaction::emojiMap()[$reactionType] ?? '🐾';
                            @endphp
                            <div class="flex min-h-14 items-center justify-between gap-3 border-b ui-border py-3 last:border-b-0" data-ui="post-reactions-row">
                                <a href="{{ route('profile.show', $reactor) }}" class="flex min-w-0 flex-1 items-center gap-3 rounded-[var(--radius-soft)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
                                    <span class="relative shrink-0">
                                        <x-ui.avatar :src="$reactor->avatar_url" :name="$reactor->name" :user="$reactor" size="sm"/>
                                        <span class="absolute -bottom-1 -right-1 inline-flex size-5 items-center justify-center rounded-full border border-white bg-cream text-xs" aria-hidden="true">{{ $reactionEmoji }}</span>
                                    </span>
                                    <span class="min-w-0">
                                        <span class="block truncate text-sm font-semibold ui-text">{{ $reactor->name }}</span>
                                        <span class="block truncate text-xs shell-text-muted">&#64;{{ $reactor->username }}</span>
                                    </span>
                                </a>

                                @auth
                                    @if ((int) auth()->id() !== (int) $reactor->getKey())
                                        <x-follow-button :user="$reactor" :follow-status="$row['follow_status']" size="sm"/>
                                    @endif
                                @endauth
                            </div>
                        @empty
                            <p class="py-8 text-center text-sm shell-text-muted">No reactions yet.</p>
                        @endforelse

                        @if ($this->hasMore())
                            <div class="pt-3 text-center">
                                <x-ui.button type="button" size="sm" variant="secondary" wire:click="loadMore">
                                    Load more
                                </x-ui.button>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
