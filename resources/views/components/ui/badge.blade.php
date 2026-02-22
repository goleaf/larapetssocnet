@props([
    'variant' => 'default',
    'size' => 'md',
    'dot' => false,
    'pill' => true,
])
@php
    $baseClasses = 'inline-flex items-center justify-center font-medium whitespace-nowrap';

    $variants = [
        'default' => 'bg-cream text-fur border border-whisker',
        'primary' => 'bg-paw-light text-paw-dark border border-paw-light',
        'success' => 'bg-leaf-light text-leaf border border-leaf-light',
        'danger' => 'bg-rose-light text-rose border border-rose-light',
        'warning' => 'bg-amber-light text-amber border border-amber-light',
        'info' => 'bg-sky-light text-sky border border-sky-light',
        'dark' => 'bg-bark text-cream border border-bark',
    ];

    $sizes = [
        'sm' => 'px-2 py-0.5 text-2xs',
        'md' => 'px-2.5 py-1 text-xs',
    ];

    $dotColors = [
        'default' => 'bg-fur',
        'primary' => 'bg-paw-dark',
        'success' => 'bg-leaf',
        'danger' => 'bg-rose',
        'warning' => 'bg-amber',
        'info' => 'bg-sky',
        'dark' => 'bg-cream',
    ];

    $classes = \Illuminate\Support\Arr::toCssClasses([
        $baseClasses,
        $variants[$variant] ?? $variants['default'],
        $sizes[$size] ?? $sizes['md'],
        $pill ? 'rounded-pill' : 'rounded-md',
        $dot ? 'gap-1.5' : '',
    ]);
@endphp

<span {{ $attributes->merge(['class' => $classes]) }}>
    @if($dot)
        <span class="w-1.5 h-1.5 rounded-pill {{ $dotColors[$variant] ?? 'bg-fur' }}"></span>
    @endif
    {{ $slot }}
</span>
