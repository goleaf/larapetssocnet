@props([
    'items' => [],
])

<nav {{ $attributes->class(['flex items-center gap-1.5 text-xs text-fur']) }} aria-label="Breadcrumb">
@foreach ($items as $item)
        @if (!$loop->first)
            <span class="text-whisker" aria-hidden="true">›</span>
        @endif
        @if ($loop->last || empty($item['href']))
            <span class="font-medium text-bark truncate">{{ $item['label'] ?? '' }}</span>
          @else
        <a href="{{ $item['href'] }}" class="hover:text-paw transition-colors truncate">
                {{ $item['label'] ?? '' }}
                </a>
    @endif
@endforeach
</nav>
