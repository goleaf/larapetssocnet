@props(['type'=>'public'])

@php
 $normalizedType = strtolower((string) $type);

 $config = match ($normalizedType) {
'private'=> ['tone'=>'warning','label'=>'🔒 Private'],
'secret'=> ['tone'=>'dark','label'=>'🕵️ Secret'],
 default => ['tone'=>'success','label'=>'🌍 Public'],
 };
@endphp

<x-ui.badge :tone="$config[' tone']" {{ $attributes }}>
 {{ $config['label'] }}
</x-ui.badge>
