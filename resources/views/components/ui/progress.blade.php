@props([
    'value' => 0,
    'label' => null,
    'color' => 'paw',
])

@php
    $value = max(0, min(100, (int) $value));
    
    $colors = [
        'paw' => 'bg-paw',
        'leaf' => 'bg-leaf',
        'sky' => 'bg-sky',
        'rose' => 'bg-rose',
    ];
    
    $colorClass = $colors[$color] ?? $colors['paw'];
@endphp

<div {{ $attributes->merge(['class' => 'w-full']) }}>
    @if($label)
        <div class="flex justify-between items-end mb-1.5">
            <span class="text-sm font-medium text-bark">{{ $label }}</span>
            <span class="text-xs font-medium text-fur">{{ $value }}%</span>
        </div>
    @endif
    
    <div class="w-full h-2 bg-whisker/30 rounded-pill overflow-hidden">
        <div class="h-full rounded-pill transition-all duration-500 ease-out {{ $colorClass }}" style="width: {{ $value }}%"></div>
    </div>
</div>
