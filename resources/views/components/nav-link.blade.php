@props(['active'])

@php
    $classes = ($active ?? false)
        ? 'shell-nav-link active'
        : 'shell-nav-link';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
