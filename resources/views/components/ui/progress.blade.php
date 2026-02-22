@props([
    'value' => 0,
    'label' => null,
    'color' => 'paw',
])

@php
    $clampedValue = max(0, min(100, (int) $value));

    $barColor = [
        'paw'  => 'bg-paw',
        'leaf' => 'bg-leaf',
        'sky'  => 'bg-sky',
        'rose' => 'bg-rose',
    ][$color] ?? 'bg-paw';
@endphp

<div {{ $attributes->class(['w-full']) }}>
    @if (filled($label))
        <div class="flex justify-between items-center mb-1.5">
            <span class="text-xs font-medium text-fur">{{ $label }}</span>
            <span class="text-xs font-semibold text-bark">{{ $clampedValue }}%</span>
        </div>
    @endif

    <div class="w-full h-2 bg-cream rounded-pill overflow-hidden">
        <div
            class="{{ $barColor }} h-full rounded-pill transition-all duration-500 ease-out"
            style="width: {{ $clampedValue }}%"
            role="progressbar"
            aria-valuenow="{{ $clampedValue }}"
            aria-valuemin="0"
            aria-valuemax="100"
        ></div>
    </div>
</div>
