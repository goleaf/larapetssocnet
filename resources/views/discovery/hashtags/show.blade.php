@push('meta')
    @php
        $canonicalUrl = route('hashtags.show', $hashtag);
        $title = '#'.$hashtag->name.' · '.config('app.name', 'LaraPets');
        $description = 'Posts tagged with #'.$hashtag->name.'.';
    @endphp
    <link rel="canonical" href="{{ $canonicalUrl }}">
    <meta name="description" content="{{ $description }}">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ $canonicalUrl }}">
@endpush

<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header
            :title="'#'.$hashtag->name"
            :description="number_format((int) $hashtag->posts_count).' posts'"
            icon="🏷️"
        />
    </x-slot>

    @php
        $sortTabs = [
            ['label' => 'Latest', 'value' => 'latest'],
            ['label' => 'Trending', 'value' => 'trending'],
            ['label' => 'Top', 'value' => 'top'],
        ];
    @endphp

    <x-ui.card class="mb-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <x-ui.tabs :tabs="$sortTabs" :active="$sort" paramName="sort" class="mb-0"/>
            <x-ui.button
                href="{{ route('explore.index', ['q' => '#'.$hashtag->name]) }}"
                variant="ghost"
                size="sm"
            >
                Back to Explore
            </x-ui.button>
        </div>
    </x-ui.card>

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-[minmax(0,1fr)_18rem]">
        <div class="space-y-5">
            @forelse ($posts as $post)
                <x-post-card :post="$post" context="explore"/>
            @empty
                <x-ui.empty-state
                    title="No posts found"
                    description="Be the first to post with #{{ $hashtag->name }}."
                    icon="🪄"
                />
            @endforelse

            @if ($posts->hasPages())
                <div>
                    {{ $posts->links() }}
                </div>
            @endif
        </div>

        <aside class="space-y-4">
            <x-ui.card>
                <x-slot name="header">
                    <x-ui.card-header
                        title="Related hashtags"
                        subtitle="Discover more topics"
                    />
                </x-slot>

                <div class="space-y-2">
                    @forelse ($relatedHashtags as $related)
                        <a
                            href="{{ route('hashtags.show', $related) }}"
                            class="flex items-center justify-between rounded-xl border border-whisker/30 bg-warm-white px-3 py-2 text-sm font-semibold text-bark hover:bg-cream transition-colors"
                        >
                            <span>#{{ $related->name }}</span>
                            <span class="text-xs text-fur">{{ number_format((int) $related->posts_count) }}</span>
                        </a>
                    @empty
                        <p class="text-sm text-fur">No related hashtags yet.</p>
                    @endforelse
                </div>
            </x-ui.card>
        </aside>
    </div>
</x-app-layout>
