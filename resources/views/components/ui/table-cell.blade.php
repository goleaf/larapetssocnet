@props([
    'align' => 'left',
    'compact' => false,
])

@php
    $alignClass = match ((string) $align) {
        'center' => 'text-center',
        'right' => 'text-right',
        default => 'text-left',
    };

    $paddingClass = $compact ? 'px-3 py-2' : 'px-4 py-3';
@endphp

<td {{ $attributes->merge(['class' => $paddingClass . ' ' . $alignClass . ' text-bark']) }}>
    {{ $slot }}
</td>
