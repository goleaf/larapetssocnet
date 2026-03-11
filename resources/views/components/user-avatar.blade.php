@props([
    'user',
    'size' => 'md',
])

<x-avatar
    :name="$user?->name"
    :src="$user?->avatar_url"
    :size="$size"
    :alt="$user?->name ? $user->name.' avatar' : __('pets.owner')"
/>
