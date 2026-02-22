@props([
    'label' => '',
    'value' => '',
    'icon' => null,
    'trend' => null,
    'trendUp' => true,
])

<div {{ $attributes->class(['flex flex-col gap-1']) }}>
    <div class="flex items-center gap-2">
        @if (filled($icon))
            <span class="text-lg text-fur" aria-hidden="true">{{ $icon }}</span>
        @endif
        <span class="text-2xl font-bold font-display text-bark leading-tight">{{ $value }}</span>
        @if (filled($trend))
            <span @class([
                'inline-flex items-center text-xs font-semibold px-1.5 py-0.5 rounded-pill',
                'bg-leaf-light text-leaf' => $trendUp,
                'bg-rose-light text-rose' => ! $trendUp,
            ])>
                @if ($trendUp) ↑ @else ↓ @endif
                {{ $trend }}
            </span>
        @endif
    </div>
    <span class="text-xs font-medium text-fur uppercase tracking-wide">{{ $label }}</span>
</div>
