@props([
    'visibility' => 'public',
])

@if($visibility === 'followers')
    <span class="inline-flex items-center gap-1 rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">
        👥 Followers
    </span>
@elseif($visibility === 'private')
    <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-400">
        🔒 Only me
    </span>
@endif
