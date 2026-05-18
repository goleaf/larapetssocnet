@props([
    'visibility' => 'public',
])

@if($visibility === 'followers')
    <span class="ui-token">
        👥 Followers
    </span>
@elseif($visibility === 'private')
    <span class="ui-token">
        🔒 Only me
    </span>
@endif
