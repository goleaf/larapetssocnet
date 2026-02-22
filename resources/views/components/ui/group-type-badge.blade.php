@props(['type' => 'public'])

@php
    $config = match (strtolower($type)) {
        'private' => ['variant' => 'warning', 'label' => '🔒 Private'],
        'secret' => ['variant' => 'dark', 'label' => '🕵️ Secret'],
        default => ['variant' => 'success', 'label' => '🌍 Public'],
    };
@endphp

<x-ui.badge :variant="$config['variant']" {{ $attributes }}>
    {{ $config['label'] }}
</x-ui.badge>