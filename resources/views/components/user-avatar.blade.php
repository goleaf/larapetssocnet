@props([
    'user',
    'size' => 'md',
])

<x-avatar
    :name="$user?->name"
    :src="$user?->avatar_url"
    :size="$size"
    :user="$user"
    :alt="$user?->name ? $user->name.' avatar' : __('pets.owner')"
/>
