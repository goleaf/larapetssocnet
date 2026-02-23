@props([
    'href' => null,
    'icon' => null,
    'variant' => 'default',
    'disabled' => false,
])

@php
    $classes = \Illuminate\Support\Arr::toCssClasses([
        'flex w-full items-center gap-2 border-l-2 px-4 py-2 text-left text-sm font-medium transition-colors',
        $variant === 'danger'
            ? 'border-transparent text-rose hover:border-rose hover:bg-rose-light'
            : 'border-transparent text-bark hover:border-paw hover:bg-cream',
        $disabled ? 'pointer-events-none cursor-not-allowed opacity-50' : '',
        $attributes->get('class'),
    ]);
@endphp

@if($href)
    <a
        href="{{ $href }}"
        {{ $attributes->except('class')->merge(['class' => $classes]) }}
        @if($disabled)
            aria-disabled="true"
            tabindex="-1"
        @endif
    >
        @if($icon)
            <span class="flex h-4 w-4 shrink-0 items-center justify-center text-inherit">{{ $icon }}</span>
        @endif

        {{ $slot }}
    </a>
@else
    <button
        type="{{ $attributes->get('type', 'button') }}"
        {{ $attributes->except(['class', 'type'])->merge(['class' => $classes]) }}
        @if($disabled)
            disabled
        @endif
    >
        @if($icon)
            <span class="flex h-4 w-4 shrink-0 items-center justify-center text-inherit">{{ $icon }}</span>
        @endif

        {{ $slot }}
    </button>
@endif
