@props([
    'paginator',
])

@php
    $hasTotal = method_exists($paginator, 'total');
    $lastPage = method_exists($paginator, 'lastPage') ? (int) $paginator->lastPage() : null;
    $currentPage = method_exists($paginator, 'currentPage') ? (int) $paginator->currentPage() : 1;

    $paginationItems = collect();

    if ($lastPage !== null && $lastPage > 0) {
        if ($lastPage <= 7) {
            $pages = range(1, $lastPage);
        } else {
            $windowStart = max(1, $currentPage - 1);
            $windowEnd = min($lastPage, $currentPage + 1);

            $pages = collect([1, $windowStart, $windowStart + 1, $windowEnd - 1, $windowEnd, $lastPage])
                ->filter(static fn (int $page): bool => $page >= 1 && $page <= $lastPage)
                ->unique()
                ->sort()
                ->values();

            $paginationItems = collect();
            $previousPage = null;

            foreach ($pages as $page) {
                if ($previousPage !== null && $page - $previousPage > 1) {
                    $paginationItems->push(['type' => 'ellipsis']);
                }

                $paginationItems->push([
                    'type' => 'page',
                    'number' => $page,
                    'url' => $paginator->url($page),
                    'active' => $page === $currentPage,
                ]);

                $previousPage = $page;
            }
        }

        if ($paginationItems->isEmpty()) {
            $paginationItems = collect($pages)->map(fn (int $page): array => [
                'type' => 'page',
                'number' => $page,
                'url' => $paginator->url($page),
                'active' => $page === $currentPage,
            ]);
        }
    }
@endphp

@if ($paginator->hasPages())
    <div class="mt-4 flex items-center justify-between border-t border-whisker/30 px-1 py-3" {{ $attributes }}>
        <div class="flex flex-1 justify-between sm:hidden">
            @if ($paginator->onFirstPage())
                <x-ui.button variant="outline" size="sm" disabled>Previous</x-ui.button>
            @else
                <x-ui.button variant="outline" size="sm" :href="$paginator->previousPageUrl()">Previous</x-ui.button>
            @endif

            @if ($paginator->hasMorePages())
                <x-ui.button variant="outline" size="sm" :href="$paginator->nextPageUrl()">Next</x-ui.button>
            @else
                <x-ui.button variant="outline" size="sm" disabled>Next</x-ui.button>
            @endif
        </div>

        <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
            <div>
                @if($hasTotal)
                    <p class="text-sm text-fur">
                        Showing
                        <span class="font-medium text-bark">{{ $paginator->firstItem() }}</span>
                        to
                        <span class="font-medium text-bark">{{ $paginator->lastItem() }}</span>
                        of
                        <span class="font-medium text-bark">{{ $paginator->total() }}</span>
                        results
                    </p>
                @else
                    <p class="text-sm text-fur">Page <span class="font-medium text-bark">{{ $currentPage }}</span></p>
                @endif
            </div>

            <div>
                <nav class="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
                    @if ($paginator->onFirstPage())
                        <span class="relative inline-flex cursor-not-allowed items-center rounded-l-md bg-cream/50 px-2 py-2 text-whisker ring-1 ring-inset ring-whisker/50">
                            <span class="sr-only">Previous</span>
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 0 1-.02 1.06L8.832 10l3.938 3.71a.75.75 0 1 1-1.04 1.08l-4.5-4.25a.75.75 0 0 1 0-1.08l4.5-4.25a.75.75 0 0 1 1.06.02z" clip-rule="evenodd" />
                            </svg>
                        </span>
                    @else
                        <a href="{{ $paginator->previousPageUrl() }}" class="relative inline-flex items-center rounded-l-md px-2 py-2 text-fur ring-1 ring-inset ring-whisker/50 transition-colors hover:bg-cream hover:text-bark focus:z-20 focus:outline-offset-0">
                            <span class="sr-only">Previous</span>
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 0 1-.02 1.06L8.832 10l3.938 3.71a.75.75 0 1 1-1.04 1.08l-4.5-4.25a.75.75 0 0 1 0-1.08l4.5-4.25a.75.75 0 0 1 1.06.02z" clip-rule="evenodd" />
                            </svg>
                        </a>
                    @endif

                    @foreach ($paginationItems as $item)
                        @if ($item['type'] === 'ellipsis')
                            <span class="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-fur ring-1 ring-inset ring-whisker/50">…</span>
                        @elseif ($item['active'])
                            <span aria-current="page" class="relative z-10 inline-flex items-center bg-paw px-4 py-2 text-sm font-semibold text-white">{{ $item['number'] }}</span>
                        @else
                            <a href="{{ $item['url'] }}" class="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-fur ring-1 ring-inset ring-whisker/50 transition-colors hover:bg-cream hover:text-bark focus:z-20 focus:outline-offset-0">{{ $item['number'] }}</a>
                        @endif
                    @endforeach

                    @if ($paginator->hasMorePages())
                        <a href="{{ $paginator->nextPageUrl() }}" class="relative inline-flex items-center rounded-r-md px-2 py-2 text-fur ring-1 ring-inset ring-whisker/50 transition-colors hover:bg-cream hover:text-bark focus:z-20 focus:outline-offset-0">
                            <span class="sr-only">Next</span>
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L11.168 10 7.23 6.29a.75.75 0 1 1 1.04-1.08l4.5 4.25a.75.75 0 0 1 0 1.08l-4.5 4.25a.75.75 0 0 1-1.06-.02z" clip-rule="evenodd" />
                            </svg>
                        </a>
                    @else
                        <span class="relative inline-flex cursor-not-allowed items-center rounded-r-md bg-cream/50 px-2 py-2 text-whisker ring-1 ring-inset ring-whisker/50">
                            <span class="sr-only">Next</span>
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L11.168 10 7.23 6.29a.75.75 0 1 1 1.04-1.08l4.5 4.25a.75.75 0 0 1 0 1.08l-4.5 4.25a.75.75 0 0 1-1.06-.02z" clip-rule="evenodd" />
                            </svg>
                        </span>
                    @endif
                </nav>
            </div>
        </div>
    </div>
@endif
