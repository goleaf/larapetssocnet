@props([
'post',
'myReactions' => collect(),
'mySaved' => collect(),
'showComments' => false,
'compact' => false,
'context' => 'feed',
])

@include('partials.post-card', [
 'post' => $post,
 'viewer' => auth()->user(),
])
