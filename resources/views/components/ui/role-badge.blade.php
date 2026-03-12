@props(['role'=>'member'])

<x-ui.badge :tone="match (strtolower((string) $role)) {
 'owner' => 'dark',
 'admin' => 'danger',
 'moderator' => 'warning',
 default => 'default',
}" {{ $attributes }}>
 {{ match (strtolower((string) $role)) {
 'owner' => '👑 Owner',
 'admin' => '🛡️ Admin',
 'moderator' => '⚡ Moderator',
 default => 'Member',
} }}
</x-ui.badge>
