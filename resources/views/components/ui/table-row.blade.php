@props([
    'hover' => true,
    'highlight' => false,
    'deleted' => false,
])

<tr {{ $attributes->merge([
    'class' => \Illuminate\Support\Arr::toCssClasses([
        $hover ? 'transition-colors hover:bg-cream' : '',
        $highlight ? 'bg-paw-light/30' : '',
        $deleted ? 'opacity-70' : '',
    ]),
]) }}>
    {{ $slot }}
</tr>
