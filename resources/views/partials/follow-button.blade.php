@props([
'user',
'status'=> null,
])

@if (auth()->user() && $user && auth()->id() !== $user->getKey())
 <form
 action="{{ route('users.follow', $user->username) }}"
 method="POST"
 >
 @csrf

 <button
 type="submit"
 data-testid="follow-toggle"
 class="inline-flex items-center justify-center px-3 py-1.5 text-xs font-semibold transition-colors {{ in_array(($status ?? auth()->user()?->getFollowStatus($user)), ['following','pending'], true) ? 'border border-[color:var(--ui-border)] bg-[color:var(--ui-surface)] text-[color:var(--ui-text)] hover:bg-[color:var(--ui-surface-muted)]' : 'border border-transparent bg-paw text-white hover:bg-paw-dark' }}"
 >
 {{ match (($status ?? auth()->user()?->getFollowStatus($user))) {
    'following' => 'Unfollow',
    'pending' => 'Cancel Request',
    default => 'Follow',
 } }}
 </button>
 </form>
@endif
