@props([
    'deleted' => false,
])

<tr {{ $attributes->class([
    'transition-colors duration-150 hover:bg-white/30 dark:hover:bg-black/10',
    'opacity-65' => $deleted,
]) }}>
    {{ $slot }}
</tr>
