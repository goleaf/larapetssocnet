@props([
    'role' => 'member',
])
@php
    $config = [
        'owner' => ['variant' => 'dark', 'label' => '👑 Owner'],
        'admin' => ['variant' => 'danger', 'label' => '🛡️ Admin'],
        'moderator' => ['variant' => 'warning', 'label' => '⚡ Moderator'],
        'member' => ['variant' => 'default', 'label' => 'Member'],
    ][$role] ?? ['variant' => 'default', 'label' => ucfirst((string) $role)];
@endphp

<x-ui.badge :variant="$config['variant']" size="sm" {{ $attributes }}>
    {{ $config['label'] }}
</x-ui.badge>
