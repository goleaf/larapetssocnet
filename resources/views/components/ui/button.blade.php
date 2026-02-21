@props([
    'variant' => 'primary',
    'size' => 'md',
])

@php
    $variantClasses = [
        'primary' => 'btn-primary',
        'ghost' => 'btn-ghost',
        'danger' => 'btn-danger',
    ][$variant] ?? 'btn-primary';

    $sizeClasses = [
        'sm' => 'px-3 py-2 text-xs',
        'md' => 'px-3.5 py-2 text-sm',
        'lg' => 'px-4 py-2.5 text-sm',
    ][$size] ?? 'px-3.5 py-2 text-sm';
@endphp

<button
    {{ $attributes->class([
        'btn-base',
        $variantClasses,
        $sizeClasses,
    ]) }}
>
    {{ $slot }}
</button>
