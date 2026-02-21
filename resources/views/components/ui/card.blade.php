@props([
    'padding' => 'default',
])

@php
    $paddingClasses = [
        'none' => '',
        'sm' => 'p-3',
        'default' => 'p-4 sm:p-5',
        'lg' => 'p-5 sm:p-6',
    ][$padding] ?? $padding;
@endphp

<section
    {{ $attributes->class(array_filter([
        'shell-card',
        $paddingClasses,
    ])) }}
>
    {{ $slot }}
</section>
