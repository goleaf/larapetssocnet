@props([
    'items' => [],
])

<nav class="flex" aria-label="Breadcrumb">
    <ol role="list" class="flex items-center space-x-2">
        @foreach($items as $index => $item)
            <li>
                <div class="flex items-center">
                    @if($index > 0)
                        <svg class="h-5 w-5 shrink-0 text-whisker mr-2" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                            <path d="M5.555 17.776l8-16 .894.448-8 16-.894-.448z" />
                        </svg>
                    @endif
                    
                    @php
                        $isLast = $index === count($items) - 1;
                        $label = is_array($item) ? ($item['label'] ?? '') : $item;
                        $href = is_array($item) ? ($item['href'] ?? null) : null;
                    @endphp
                    
                    @if($href && !$isLast)
                        <a href="{{ $href }}" class="text-sm font-medium text-fur hover:text-bark transition-colors">{{ $label }}</a>
                    @else
                        <span class="text-sm font-medium {{ $isLast ? 'text-bark' : 'text-fur' }}" @if($isLast) aria-current="page" @endif>{{ $label }}</span>
                    @endif
                </div>
            </li>
        @endforeach
    </ol>
</nav>
