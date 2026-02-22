@props([
    'type' => 'public',
])
@php
    $config = [
        'public' => ['variant' => 'success', 'label' => '🌍 Public'],
        'private' => ['variant' => 'warning', 'label' => '🔒 Private'],
        'secret' => ['variant' => 'dark', 'label' => '🕵️ Secret'],
    ][$type] ?? ['variant' => 'default', 'label' => ucfirst((string) $type)];
@endphp

<x-ui.badge :variant="$config['variant']" size="sm" {{ $attributes }}>
    {{ $config['label'] }}
</x-ui.badge>
