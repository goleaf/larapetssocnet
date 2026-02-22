@props([
    'title' => null,
    'subtitle' => null,
    'tight' => false,
])

<div {{ $attributes->merge(['class' => $tight ? 'mb-4' : 'mb-8']) }}>
    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 border-b border-whisker/40 pb-4">
        <div>
            @if($title)
                <h2 class="text-2xl font-bold font-display text-bark">{{ $title }}</h2>
            @endif
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
    
    <div class="pt-6">
        {{ $slot }}
    </div>
</div>
