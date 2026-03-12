@props(['value'])

<x-ui.label :value="$value" {{ $attributes }}>
 {{ $slot }}
</x-ui.label>
