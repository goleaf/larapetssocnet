@props([
    'title',
    'subtitle' => null,
    'breadcrumbs' => [],
])

<div {{ $attributes->merge(['class' => 'flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-8']) }}>
    <div>
        @if(!empty($breadcrumbs))
            <nav class="flex items-center gap-1.5 text-xs text-whisker mb-2" aria-label="Breadcrumb">
                @foreach($breadcrumbs as $index => $crumb)
                    @php
                        $label = is_array($crumb) ? ($crumb['label'] ?? '') : $crumb;
                        $href = is_array($crumb) ? ($crumb['href'] ?? null) : null;
                        $isLast = $index === count($breadcrumbs) - 1;
                    @endphp
                    
                    @if(!$isLast && $href)
                        <a href="{{ $href }}" class="text-fur hover:text-paw transition-colors">{{ $label }}</a>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-3.5 h-3.5">
                            <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
                        </svg>
                    @else
                        <span class="text-bark font-medium">{{ $label }}</span>
                    @endif
                @endforeach
            </nav>
        @endif
        
        <h1 class="text-3xl font-bold font-display text-bark">{{ $title }}</h1>
        
        @if($subtitle)
            <p class="text-sm text-fur mt-1">{{ $subtitle }}</p>
        @endif
    </div>
    
    @if(isset($action))
        <div class="shrink-0 mb-1">
            {{ $action }}
        </div>
    @endif
</div>
