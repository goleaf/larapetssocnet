@props([
    'variant' => 'ghost',
    'size' => 'md',
    'href' => null,
    'title' => null,
    'disabled' => false,
])
@php
    $variantClasses = [
        'primary' => 'bg-paw text-white hover:bg-paw-dark shadow-button',
        'secondary' => 'bg-paw-light text-paw-dark hover:bg-orange-200',
        'ghost' => 'bg-warm-white text-bark border border-whisker/40 hover:bg-cream',
        'danger' => 'bg-rose-light text-rose hover:bg-rose hover:text-white',
        'success' => 'bg-leaf-light text-leaf hover:bg-leaf hover:text-white',
    ][$variant] ?? 'bg-warm-white text-bark border border-whisker/40 hover:bg-cream';

    $sizeClasses = [
        'xs' => 'h-6 w-6 text-xs',
        'sm' => 'h-8 w-8 text-sm',
        'md' => 'h-9 w-9 text-sm',
        'lg' => 'h-11 w-11 text-base',
    ][$size] ?? 'h-9 w-9 text-sm';

    $tag = filled($href) ? 'a' : 'button';
@endphp

<{{ $tag }}
    {{ $attributes->class([
    'inline-flex items-center justify-center rounded-pill transition-all duration-150',
    $variantClasses,
    $sizeClasses,
    'opacity-50 cursor-not-allowed pointer-events-none' => $disabled,
]) }}
@if (filled($href))
    href="{{ $href }}"
@else
        type="button"
    @endif
    @if (filled($title))
        title="{{ $title }}"
        aria-label="{{ $title }}"
    @endif
    @disabled($disabled && $tag === 'button')
>
    {{ $slot }}
</{{ $tag }}>
