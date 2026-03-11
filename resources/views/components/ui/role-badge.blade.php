@props(['role'=>'member'])

@php
 $normalizedRole = strtolower((string) $role);

 $config = match ($normalizedRole) {
'owner'=> ['tone'=>'dark','label'=>'👑 Owner'],
'admin'=> ['tone'=>'danger','label'=>'🛡️ Admin'],
'moderator'=> ['tone'=>'warning','label'=>'⚡ Moderator'],
 default => ['tone'=>'default','label'=>'Member'],
 };
@endphp

<x-ui.badge :tone="$config['tone']" {{ $attributes }}>
 {{ $config['label'] }}
</x-ui.badge>
