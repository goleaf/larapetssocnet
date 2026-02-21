@props([
    'variant' => 'info',
])

@php
    $variantClasses = [
        'success' => 'border-emerald-300/80 bg-emerald-500/10 text-emerald-800',
        'warning' => 'border-amber-300/80 bg-amber-500/10 text-amber-800',
        'danger' => 'border-rose-300/80 bg-rose-500/10 text-rose-800',
        'info' => 'border-sky-300/80 bg-sky-500/10 text-sky-800',
    ][$variant] ?? 'border-sky-300/80 bg-sky-500/10 text-sky-800';
@endphp

<div
    {{ $attributes->class([
        'rounded-2xl border px-3.5 py-3 text-sm font-medium',
        $variantClasses,
    ]) }}
    role="status"
    aria-live="polite"
>
    {{ $slot }}
</div>
