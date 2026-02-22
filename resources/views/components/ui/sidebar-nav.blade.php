@props([
    'items' => [],
    'title' => null,
])

<nav {{ $attributes->class(['space-y-1']) }} aria-label="{{ $title ?? 'Sidebar navigation' }}">
    @if (filled($title))
        <p class="px-3 text-2xs font-bold uppercase tracking-wider text-fur mb-2">{{ $title }}</p>
    @endif

    @foreach ($items as $item)
        @php
            $isActive = isset($item['route']) && request()->routeIs($item['route'] . '*');
            $href = $item['href'] ?? (isset($item['route']) ? route($item['route']) : '#');
        @endphp

        <a
            href="{{ $href }}"
            @class([
                'flex items-center gap-2.5 px-3 py-2 text-sm rounded-lg transition-all duration-150',
                'bg-paw-light text-paw-dark font-medium' => $isActive,
                'text-fur hover:bg-cream hover:text-bark' => ! $isActive,
            ])
        >
            @if (isset($item['icon']))
                <span class="shrink-0 text-base" aria-hidden="true">{{ $item['icon'] }}</span>
            @endif
            <span class="truncate">{{ $item['label'] ?? '' }}</span>
            @if (isset($item['badge']) && $item['badge'] > 0)
                <x-ui.badge variant="primary" size="sm" class="ml-auto">{{ $item['badge'] }}</x-ui.badge>
            @endif
        </a>
    @endforeach
</nav>
