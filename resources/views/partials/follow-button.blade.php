@props([
'user',
'status'=> null,
])

@php
 $viewer = auth()->user();
 $followStatus = $status;

 if ($followStatus === null && $viewer && $user) {
 $followStatus = $viewer->getFollowStatus($user);
 }

 $isFollowingState = in_array($followStatus, ['following','pending'], true);
 $label = match ($followStatus) {
'following'=>'Unfollow',
'pending'=>'Cancel Request',
 default =>'Follow',
 };

 $buttonClasses = $isFollowingState
 ?'border border-[color:var(--ui-border)] bg-[color:var(--ui-surface)] text-[color:var(--ui-text)] hover:bg-[color:var(--ui-surface-muted)]'
 :'border border-transparent bg-paw text-white hover:bg-paw-dark';
@endphp

@if ($viewer && $user && $viewer->getKey() !== $user->getKey())
 <form
 action="{{ route('users.follow', $user->username) }}"
 method="POST"
 >
 @csrf

 <button
 type="submit"
 data-testid="follow-toggle"
 class="inline-flex items-center justify-center px-3 py-1.5 text-xs font-semibold transition-colors {{ $buttonClasses }}"
 >
 {{ $label }}
 </button>
 </form>
@endif
