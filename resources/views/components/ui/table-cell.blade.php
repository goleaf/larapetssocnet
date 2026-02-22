@props([
    'align' => 'left',
])
@php
    $alignClass = match ($align) {
        'center' => 'text-center',
        'right' => 'text-right',
        default => 'text-left',
    };
@endphp

<td {{ $attributes->merge(['class' => "px-4 py-3 text-bark $alignClass"]) }}>
    {{ $slot }}
</td>
