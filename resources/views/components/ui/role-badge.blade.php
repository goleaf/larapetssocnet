@props(['role' => 'member'])

@php
    $config = match (strtolower($role)) {
        'owner' => ['variant' => 'dark', 'label' => '👑 Owner'],
        'admin' => ['variant' => 'danger', 'label' => '🛡️ Admin'],
        'moderator' => ['variant' => 'warning', 'label' => '⚡ Moderator'],
        default => ['variant' => 'default', 'label' => 'Member'],
    };
@endphp

<x-ui.badge :variant="$config['variant']" {{ $attributes }}>
    {{ $config['label'] }}
</x-ui.badge>