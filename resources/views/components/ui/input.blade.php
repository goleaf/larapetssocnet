@props([
    'type' => 'text',
    'disabled' => false,
])

<input
    type="{{ $type }}"
    @disabled($disabled)
    {{ $attributes->class(['form-input']) }}
>
