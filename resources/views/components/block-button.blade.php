@props([
    'blocked' => false,
    'busy' => false,
    'size' => 'md',
])

@php
    $sizeClasses = [
        'sm' => 'px-3 py-2 text-xs',
        'md' => 'px-4 py-2.5 text-sm',
        'lg' => 'px-5 py-3 text-sm',
    ][$size] ?? 'px-4 py-2.5 text-sm';
@endphp

<button
    type="button"
    {{ $attributes->merge(['class' => "btn-base btn-ghost {$sizeClasses}"]) }}
    @if (! $attributes->has(':disabled') && ! $attributes->has('x-bind:disabled'))
        @disabled($busy)
    @endif
    @if (! $attributes->has(':aria-pressed') && ! $attributes->has('x-bind:aria-pressed'))
        aria-pressed="{{ $blocked ? 'true' : 'false' }}"
    @endif
>
    {{ $slot }}
</button>
