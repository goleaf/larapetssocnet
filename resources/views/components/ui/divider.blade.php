@props([
    'label' => null,
])

@if (filled($label))
    <div {{ $attributes->class(['flex items-center gap-3 my-4']) }}>
        <div class="flex-1 border-t border-whisker/30"></div>
        <span class="text-xs font-medium text-fur uppercase tracking-wide shrink-0">{{ $label }}</span>
        <div class="flex-1 border-t border-whisker/30"></div>
    </div>
@else
    <hr {{ $attributes->class(['border-t border-whisker/30 my-4']) }}>
@endif
