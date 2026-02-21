@props([
    'variant' => 'neutral',
])

@php
    $variantClasses = [
        'neutral' => 'bg-slate-500/15 text-slate-700',
        'success' => 'bg-emerald-500 text-white',
        'warning' => 'bg-amber-500 text-white',
        'danger' => 'bg-rose-500 text-white',
        'primary' => 'bg-emerald-600 text-white',
    ][$variant] ?? 'bg-slate-500/15 text-slate-700';
@endphp

<span
    {{ $attributes->class([
        'inline-flex min-w-5 items-center justify-center rounded-full px-1.5 py-0.5 text-[0.65rem] font-semibold leading-5',
        $variantClasses,
    ]) }}
>
    {{ $slot }}
</span>
