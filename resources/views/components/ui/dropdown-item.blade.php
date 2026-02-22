@props([
    'href' => null,
    'icon' => null,
    'variant' => 'default',
    'disabled' => false,
])
@php
    $tag = filled($href) && !$disabled ? 'a' : 'button';
    $variantClasses = [
        'default' => 'text-bark hover:bg-cream',
        'danger' => 'text-rose hover:bg-rose-light',
    ][$variant] ?? 'text-bark hover:bg-cream';
@endphp

<{{ $tag }}
    {{ $attributes->class([
    'flex items-center gap-2 w-full px-4 py-2 text-sm transition-colors duration-150',
    $variantClasses,
    'opacity-50 cursor-not-allowed' => $disabled,
]) }}
    @if ($tag === 'a')
        href="{{ $href }}"
    @else
        type="button"
    @endif
    @disabled($disabled && $tag === 'button')
>
    @if (filled($icon))
        <span class="shrink-0 text-fur" aria-hidden="true">{!! $icon !!}</span>
    @endif
    {{ $slot }}
</{{ $tag }}>
