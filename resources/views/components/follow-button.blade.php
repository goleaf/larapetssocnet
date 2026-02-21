@props([
    'following' => false,
    'busy' => false,
    'variant' => 'primary',
    'size' => 'md',
])

@php
    $variantClasses = [
        'primary' => 'btn-primary',
        'ghost' => 'btn-ghost',
        'secondary' => 'btn-secondary',
    ][$variant] ?? 'btn-primary';

    $sizeClasses = [
        'sm' => 'px-3 py-2 text-xs',
        'md' => 'px-4 py-2.5 text-sm',
        'lg' => 'px-5 py-3 text-sm',
    ][$size] ?? 'px-4 py-2.5 text-sm';
@endphp

<button
    type="button"
    {{ $attributes->merge(['class' => "btn-base {$variantClasses} {$sizeClasses}"]) }}
    @if (! $attributes->has(':disabled') && ! $attributes->has('x-bind:disabled'))
        @disabled($busy)
    @endif
    @if (! $attributes->has(':aria-pressed') && ! $attributes->has('x-bind:aria-pressed'))
        aria-pressed="{{ $following ? 'true' : 'false' }}"
    @endif
>
    {{ $slot }}
</button>
