<?php

use App\Models\Content\Post;
use App\Services\PostAnalyticsService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

new class extends Component
{
    use AuthorizesRequests;

    public int $postId;

    public bool $open = false;

    /**
     * @var list<array{key: string, label: string, description: string, value: int}>
     */
    public array $metricCards = [];

    /**
     * @var list<array{type: string, label: string, emoji: string, count: int}>
     */
    public array $reactions = [];

    public ?string $comparisonChart = null;

    public function mount(Post $post): void
    {
        $this->postId = (int) $post->getKey();
    }

    public function open(PostAnalyticsService $analytics): void
    {
        $post = $this->findPost();

        $this->authorize('viewAnalytics', $post);

        $summary = $analytics->summary($post);

        $this->metricCards = $summary['metric_cards'];
        $this->reactions = $summary['reactions'];
        $this->comparisonChart = $summary['comparison_chart'];
        $this->open = true;
    }

    public function close(): void
    {
        $this->open = false;
    }

    private function findPost(): Post
    {
        return Post::query()
            ->whereKey($this->postId)
            ->firstOrFail();
    }
};
?>

<div class="contents">
 <x-ui.icon-button
 type="button"
 size="sm"
 variant="ghost"
 label="View post analytics"
 aria-label="View post analytics"
 wire:click="open"
 wire:loading.attr="disabled"
 wire:target="open"
 data-ui="post-card-analytics-trigger"
 :icon="'<path stroke-linecap=&quot;round&quot; stroke-linejoin=&quot;round&quot; d=&quot;M4 19V5m0 14h16M8 15V9m4 6V7m4 8v-4&quot; />'"
 />

 @if ($open)
 <div
 class="fixed inset-0 z-[70] overflow-y-auto px-4 py-6 sm:px-6"
 role="dialog"
 aria-modal="true"
 aria-labelledby="post-analytics-title-{{ $this->getId() }}"
 wire:keydown.escape="close"
 data-ui="post-analytics-modal"
 >
 <div class="fixed inset-0 bg-bark/35 backdrop-blur-sm" aria-hidden="true" wire:click="close"></div>

 <div class="relative mx-auto flex min-h-full max-w-2xl items-center justify-center">
 <div class="w-full overflow-hidden rounded-[var(--radius-panel)] border ui-border bg-white shadow-xl">
 <div class="flex items-start justify-between gap-4 border-b ui-border px-6 py-5">
 <div class="min-w-0">
 <p id="post-analytics-title-{{ $this->getId() }}" class="text-lg font-semibold ui-text">Post analytics</p>
 <p class="mt-1 text-sm leading-6 shell-text-muted">Performance metrics visible only to the author.</p>
 </div>
 <x-ui.icon-button
 type="button"
 size="sm"
 variant="ghost"
 label="Close post analytics"
 aria-label="Close post analytics"
 wire:click="close"
 :icon="'<path stroke-linecap=&quot;round&quot; stroke-linejoin=&quot;round&quot; d=&quot;M6 18 18 6M6 6l12 12&quot; />'"
 />
 </div>

 <div class="space-y-5 px-6 py-5">
 <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
 @foreach ($metricCards as $metric)
 <div class="rounded-[var(--radius-soft)] border ui-border bg-cream/60 p-4" data-ui="post-analytics-metric-{{ $metric['key'] }}">
 <p class="text-2xl font-bold ui-text">{{ number_format($metric['value']) }}</p>
 <p class="mt-1 text-sm font-semibold text-fur">{{ $metric['label'] }}</p>
 <p class="mt-1 text-xs leading-5 shell-text-muted">{{ $metric['description'] }}</p>
 </div>
 @endforeach
 </div>

 <section aria-labelledby="post-analytics-reactions-{{ $this->getId() }}">
 <h3 id="post-analytics-reactions-{{ $this->getId() }}" class="text-sm font-semibold ui-text">Reaction breakdown</h3>
 <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3">
 @foreach ($reactions as $reaction)
 <div class="flex items-center justify-between gap-3 rounded-[var(--radius-soft)] border ui-border px-3 py-2" data-ui="post-analytics-reaction-{{ $reaction['type'] }}">
 <span class="inline-flex min-w-0 items-center gap-2 text-sm font-medium text-fur">
 <span aria-hidden="true">{{ $reaction['emoji'] }}</span>
 <span>{{ $reaction['label'] }}</span>
 </span>
 <span class="text-sm font-bold ui-text">{{ number_format($reaction['count']) }}</span>
 </div>
 @endforeach
 </div>
 </section>

 @if ($comparisonChart)
 <section aria-labelledby="post-analytics-chart-{{ $this->getId() }}">
 <h3 id="post-analytics-chart-{{ $this->getId() }}" class="text-sm font-semibold ui-text">Compared with recent posts</h3>
 <div class="mt-3 overflow-hidden rounded-[var(--radius-soft)] border ui-border" data-ui="post-analytics-comparison-chart">
 {!! $comparisonChart !!}
 </div>
 </section>
 @endif
 </div>

 <div class="flex justify-end border-t ui-border bg-cream/40 px-6 py-4">
 <x-ui.button type="button" variant="secondary" wire:click="close">
 Close
 </x-ui.button>
 </div>
 </div>
 </div>
 </div>
 @endif
</div>
