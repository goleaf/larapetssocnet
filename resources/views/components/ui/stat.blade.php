@props([
    'label',
    'value',
    'icon' => null,
    'trend' => null,
    'trendUp' => true,
])

<div {{ $attributes->merge(['class' => 'bg-warm-white rounded-lg p-4 border border-whisker/30 shadow-sm flex items-start gap-4']) }}>
    @if($icon)
        <div class="w-10 h-10 rounded-pill bg-paw-light text-paw-dark flex items-center justify-center shrink-0 text-xl">
            {!! $icon !!}
        </div>
    @endif
    
    <div class="flex-1">
        <p class="text-sm text-fur font-medium">{{ $label }}</p>
        <div class="flex items-baseline gap-2 mt-1">
            <p class="text-2xl font-bold font-display text-bark">{{ $value }}</p>
            
            @if($trend)
                <x-ui.badge size="sm" :variant="$trendUp ? 'success' : 'danger'" class="font-mono">
                    {{ $trend }}
                </x-ui.badge>
            @endif
        </div>
    </div>
</div>
