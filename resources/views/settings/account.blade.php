@section('title','Account Settings')

<x-app-layout>
 <x-slot name="header">
 <div>
 <h1 class="shell-title text-xl">Account Settings</h1>
 <p class="mt-1 text-sm shell-text-muted">Manage privacy, blocked users, security, and the account danger zone.</p>
 </div>
 </x-slot>

 <div class="space-y-5">
 <section class="shell-card p-6">
 <h2 class="shell-title text-lg">Privacy</h2>
 <p class="mt-1 text-sm shell-text-muted">Control who can see your profile and content.</p>

 <div
 class="mt-4"
 x-data="{
 isPrivate: {{ $user->is_private ?'true':'false'}},
 loading: false,
 message:'',
 messageType:'',
 autoApproved: 0,
 showConfirm: false,
 pendingCount: {{ (int) $user->follow_requests_count }},
 async toggle() {
 if (this.loading) return
 if (this.isPrivate && this.pendingCount > 0) {
 this.showConfirm = true
 return
 }
 await this.executeToggle()
 },
 async executeToggle() {
 this.loading = true
 this.showConfirm = false
 try {
 const res = await fetch('{{ route('privacy.toggle') }}', {
 method:'POST',
 headers: {
'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
'Accept':'application/json'
 }
 })
 const data = await res.json()
 if (data.success) {
 this.isPrivate = data.is_private
 this.message = data.message
 this.messageType ='success'
 this.autoApproved = data.auto_approved ?? 0
 this.pendingCount = this.isPrivate ? this.pendingCount : 0
 }
 } catch (e) {
 this.message ='Something went wrong. Please try again.'
 this.messageType ='error'
 }
 this.loading = false
 }
 }"
 >
 <div class="flex items-start justify-between gap-4 rounded-xl border border-[var(--ui-border)] p-3">
 <div class="flex-1">
 <div class="flex items-center gap-2">
 <p class="text-sm font-semibold">Private account</p>
 <span
 class="rounded-full px-2 py-0.5 text-xs font-medium"
 :class="isPrivate ?'bg-amber-100 text-amber-700':'bg-emerald-100 text-emerald-700'"
 x-text="isPrivate ?'Private':'Public'"
 ></span>
 </div>
 <p class="mt-1 text-xs shell-text-muted" x-show="!isPrivate">Anyone can see your profile and discover it in Explore/search.</p>
 <p class="mt-1 text-xs shell-text-muted" x-show="isPrivate">Only approved followers can see your profile content.</p>
 </div>

 <button
 type="button"
 role="switch"
 :aria-checked="isPrivate.toString()"
 aria-label="Toggle private account"
 :disabled="loading"
 @click="toggle"
 class="relative inline-flex h-7 w-12 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 disabled:opacity-60"
 :class="isPrivate ?'bg-amber-500':'bg-gray-300'"
 >
 <span class="inline-block h-5 w-5 transform rounded-full bg-white transition" :class="isPrivate ?'translate-x-6':'translate-x-1'"></span>
 </button>
 </div>

 <div
 x-show="showConfirm"
 x-transition
 class="mt-3 rounded-xl border border-amber-300 bg-amber-50 p-3 text-sm text-amber-800"
 >
 You have <strong x-text="pendingCount"></strong> pending request(s). Making your account public will auto-approve them.
 <div class="mt-2 flex gap-2">
 <button type="button" class="btn-base btn-primary px-3 py-2 text-xs" @click="executeToggle">Confirm</button>
 <button type="button" class="btn-base btn-ghost px-3 py-2 text-xs" @click="showConfirm = false">Cancel</button>
 </div>
 </div>

 <p
 x-show="message !==''"
 class="mt-3 text-sm"
 :class="messageType ==='success'?'text-emerald-600':'text-red-600'"
 >
 <span x-text="message"></span>
 <span x-show="autoApproved > 0">(<span x-text="autoApproved"></span> pending request(s) auto-approved.)</span>
 </p>

 @if ($user->is_private && $user->follow_requests_count > 0)
 <a
 href="{{ route('follow-requests.index') }}"
 class="mt-4 flex items-center justify-between rounded-xl border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-700"
 >
 <span>Pending follow requests: {{ $user->follow_requests_count }}</span>
 <span aria-hidden="true">→</span>
 </a>
 @endif
 </div>
 </section>

 <section class="shell-card p-6" x-data="{ unblocking: null, notice:''}">
 <h2 class="shell-title text-lg">Blocked Users</h2>
 <p class="mt-1 text-sm shell-text-muted">Blocked users cannot follow you or interact with your profile.</p>

 <div class="mt-4 space-y-3">
 @forelse ($blockedUsers as $blockedUser)
 @php
 $canUnblock = filled($blockedUser->username);
 $unblockUrl = $canUnblock ? route('users.unblock', ['user'=> $blockedUser]) : null;
 @endphp
 <div class="flex items-center justify-between gap-3 rounded-xl border border-[var(--ui-border)] px-4 py-3">
 <div class="flex min-w-0 items-center gap-3">
 <x-avatar :src="$blockedUser->getFirstMediaUrl('avatar')" :name="$blockedUser->name" size="md"/>
 <div class="min-w-0">
 <p class="truncate font-semibold">{{ $blockedUser->name }}</p>
 <p class="truncate text-xs shell-text-muted">&#64;{{ $blockedUser->username }}</p>
 </div>
 </div>

 <button
 type="button"
 class="btn-base btn-ghost px-3 py-2 text-xs"
 :disabled="!{{ $canUnblock ?'true':'false'}} || unblocking === {{ $blockedUser->id }}"
 aria-label="Unblock {{ $blockedUser->name }}"
 @click="(async () => {
 if (!{{ $canUnblock ?'true':'false'}}) {
 return;
 }
 unblocking = {{ $blockedUser->id }};
 notice ='';

 try {
 const response = await fetch('{{ $unblockUrl }}', {
 method:'DELETE',
 headers: {
'Accept':'application/json',
'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
 },
 });

 const payload = await response.json();

 if (payload.success) {
 location.reload();
 return;
 }

 notice = payload.message ||'Unable to unblock this user.';
 } catch (error) {
 notice ='Unable to unblock this user.';
 } finally {
 unblocking = null;
 }
 })()"
 >
 <span x-text="unblocking === {{ $blockedUser->id }} ?'Updating...':'{{ $canUnblock ?'Unblock':'Unavailable'}}'"></span>
 </button>
 </div>
 @empty
 <x-empty-state
 icon="🛡️"
 title="No blocked users"
 description="When you block someone, they will appear here."
 class="mt-4"
 />
 @endforelse
 </div>

 <p class="mt-3 text-sm shell-text-muted" x-show="notice" x-text="notice"></p>

 <div class="mt-4">
 {{ $blockedUsers->links() }}
 </div>
 </section>

 <section class="shell-card p-6">
 @include('profile.partials.update-password-form')
 </section>

 <section class="shell-card p-6">
 @include('profile.partials.delete-user-form')
 </section>
 </div>
</x-app-layout>
