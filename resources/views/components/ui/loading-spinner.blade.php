@props([
    'size' => 'md',
    'color' => 'paw',
])

@php
    $sizeClasses = [
        'sm' => 'h-4 w-4',
        'md' => 'h-6 w-6',
        'lg' => 'h-8 w-8',
    ][$size] ?? 'h-6 w-6';

    $colorClasses = [
        'paw'   => 'text-paw',
        'white' => 'text-white',
        'fur'   => 'text-fur',
    ][$color] ?? 'text-paw';
@endphp

<svg
    {{ $attributes->class(['animate-spin', $sizeClasses, $colorClasses]) }}
    viewBox="0 0 24 24"
    fill="none"
    aria-hidden="true"
>
    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" opacity=".2" />
    <path d="M22 12a10 10 0 0 0-10-10" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
</svg>
