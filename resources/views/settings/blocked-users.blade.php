@section('title','Blocked Users')

<x-app-layout>
 <x-slot name="header">
 <div>
 <h1 class="shell-title text-xl">Blocked Users</h1>
 <p class="mt-1 text-sm shell-text-muted">Manage people you blocked. Unblocking does not restore follow relationships.</p>
 </div>
 </x-slot>

 <section class="shell-card p-6"
 x-data="{ notice:'', unblocking: null }"
 role="region"
 aria-label="Blocked users list">
 <ul class="space-y-3" aria-label="Blocked users">
 @forelse ($blocked as $blockedUser)
 <li class="flex items-center justify-between gap-3 rounded-xl border border-[var(--ui-border)] px-4 py-3"
 aria-label="Blocked user {{ $blockedUser->name }}">
 <div class="flex min-w-0 items-center gap-3">
 <x-avatar :src="$blockedUser->getFirstMediaUrl('avatar')" :name="$blockedUser->name" size="md"/>
 <div class="min-w-0">
 <p class="truncate font-semibold">{{ $blockedUser->name }}</p>
 <p class="truncate text-xs shell-text-muted">&#64;{{ $blockedUser->username }}</p>
 </div>
 </div>

 <x-block-button
 size="sm"
 :aria-label="'Unblock'.$blockedUser->name"
 :aria-disabled="'unblocking ==='.$blockedUser->id"
 :aria-busy="'unblocking ==='.$blockedUser->id"
 @click="(async () => {
 unblocking = {{ $blockedUser->id }};
 notice ='';

 try {
 const response = await fetch('{{ route('users.unblock', ['user'=> $blockedUser]) }}', {
 method:'DELETE',
 headers: {
'Accept':'application/json',
'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
 },
 });

 const payload = await response.json();
 if (payload.success) {
 window.location.reload();
 return;
 }

 notice = payload.message ||'Unable to unblock user.';
 } catch (e) {
 notice ='Unable to unblock user.';
 } finally {
 unblocking = null;
 }
 })()"
 >
 <span x-text="unblocking === {{ $blockedUser->id }} ?'Updating...':'Unblock'"></span>
 </x-block-button>
 </li>
 @empty
 <li>
 <x-ui.empty-state
 icon="🛡️"
 title="You haven't blocked anyone."
 description="When you block someone, they will appear here."
 />
 </li>
 @endforelse
 </ul>

 <p class="mt-3 text-sm shell-text-muted" role="status" aria-live="polite" x-text="notice" x-show="notice"></p>

 <div class="mt-4">
 {{ $blocked->links() }}
 </div>
 </section>
</x-app-layout>
