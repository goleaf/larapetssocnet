@props([
    'spacing' => 'md',
])

@php
    $spacingClass = [
        'sm' => 'space-y-4',
        'md' => 'space-y-5',
        'lg' => 'space-y-6',
    ][(string) $spacing] ?? 'space-y-5';
@endphp

<div {{ $attributes->merge([
    'class' => \Illuminate\Support\Arr::toCssClasses([
        'w-full min-w-0',
        $spacingClass,
    ]),
    'data-ui' => 'page-stack',
]) }}>
    {{ $slot }}
</div>
