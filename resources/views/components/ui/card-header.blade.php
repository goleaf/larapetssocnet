@props([
    'title',
    'subtitle' => null,
    'icon' => null,
])

<div {{ $attributes->merge(['class' => 'flex justify-between items-start border-b border-whisker/40 pb-4 mb-4']) }}>
    <div class="flex items-start gap-3">
        @if($icon)
            <div class="mt-0.5 text-2xl">{{ $icon }}</div>
        @endif
        <div>
            <h3 class="text-base font-semibold font-display text-bark">{{ $title }}</h3>
            @if($subtitle)
                <p class="text-sm text-fur">{{ $subtitle }}</p>
            @endif
        </div>
    </div>
    
    @if(isset($action))
        <div class="shrink-0 ml-4">
            {{ $action }}
        </div>
    @endif
</div>
