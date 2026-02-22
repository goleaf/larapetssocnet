@props([
    'align' => 'left',
])
@php
    $alignClasses = [
        'left' => 'text-left',
        'center' => 'text-center',
        'right' => 'text-right',
    ][$align] ?? 'text-left';
@endphp

<td {{ $attributes->class(['px-4 py-3 text-bark text-sm', $alignClasses]) }}>
    {{ $slot }}
</td>
