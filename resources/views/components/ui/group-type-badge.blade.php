@props(['type'=>'public'])

<x-ui.badge :tone="match (strtolower((string) $type)) {
 'private' => 'warning',
 'secret' => 'dark',
 default => 'success',
}" {{ $attributes }}>
 {{ match (strtolower((string) $type)) {
 'private' => '🔒 Private',
 'secret' => '🕵️ Secret',
 default => '🌍 Public',
} }}
</x-ui.badge>
