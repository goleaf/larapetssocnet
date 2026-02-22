@props([
    'hover' => true,
    'highlight' => false,
])

    <tr {{ $attributes->merge([
    'class' => \Illuminate\Support\Arr::toCssClasses([
        $hover ? 'hover:bg-cream transition-colors' : '',
        $highlight ? 'bg-paw-light/30' : '',
    ])
]) }}>
    {{ $slot }}
</tr>
