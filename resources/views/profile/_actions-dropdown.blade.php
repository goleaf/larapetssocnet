@props([
'user',
'isBlocked'=> false,
'profileUrl'=> null,
'messageUrl'=> null,
])

@php
 $resolvedProfileUrl = $profileUrl ?: $user->profile_url;
 $resolvedMessageUrl = $messageUrl === null
 ? (Route::has('messages.conversation') ? route('messages.conversation', ['peer'=> $user]) : null)
 : $messageUrl;
@endphp

<div class="relative" x-data="{
 open: false,
 confirmBlock: false,
 copied: false,
 copyTimer: null,
 profileUrl: @js($resolvedProfileUrl),
 async copyProfileUrl() {
 if (! this.profileUrl) {
 return;
 }

 try {
 if (navigator.clipboard && window.isSecureContext) {
 await navigator.clipboard.writeText(this.profileUrl);
 } else {
 const fallback = this.$refs.profileUrlFallback;
 fallback.value = this.profileUrl;
 fallback.select();
 document.execCommand('copy');
 }

 this.copied = true;
 clearTimeout(this.copyTimer);
 this.copyTimer = setTimeout(() => {
 this.copied = false;
 }, 2000);
 window.dispatchFlash?.('Profile URL copied.', 'success');
 } catch (error) {
 this.copied = false;
 window.dispatchFlash?.('Unable to copy profile URL.', 'error');
 }

 this.open = false;
 },
}" @click.outside="open = false" @keydown.escape.window="open = false">
 <textarea x-ref="profileUrlFallback" class="fixed -left-[9999px] top-0 h-px w-px opacity-0" readonly tabindex="-1" aria-hidden="true"></textarea>

 <button
 type="button"
 data-ui="profile-actions-menu-trigger"
 class="btn-base btn-ghost min-h-11 px-3 py-2 text-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw"
 aria-label="Profile actions"
 aria-haspopup="menu"
 x-bind:aria-expanded="open.toString()"
 @click="open = !open"
 >
 •••
 </button>

 <div
 x-show="open"
 x-cloak
 x-transition
 data-ui="profile-actions-menu"
 class="absolute right-0 z-20 mt-2 w-56 rounded-xl border border-[var(--ui-border)] bg-[color:var(--ui-surface)] p-2 shadow-lg"
 role="menu"
 aria-label="Profile action menu"
 >
 @if ($resolvedMessageUrl)
 <a href="{{ $resolvedMessageUrl }}" data-ui="profile-actions-menu-message" class="flex min-h-11 items-center rounded-lg px-3 py-2 text-sm font-semibold hover:bg-emerald-500/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw" role="menuitem">Send Message</a>
 @else
 <button type="button" data-ui="profile-actions-menu-message" class="flex min-h-11 w-full items-center rounded-lg px-3 py-2 text-left text-sm font-semibold text-fur opacity-60" disabled aria-disabled="true" role="menuitem">Send Message</button>
 @endif

 <button type="button" data-ui="profile-actions-menu-suggest" class="flex min-h-11 w-full items-center rounded-lg px-3 py-2 text-left text-sm font-semibold hover:bg-emerald-500/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw" role="menuitem" @click="open = false; window.dispatchFlash?.('Suggestion tools are coming soon.', 'info')">Suggest to Friends</button>

 <template x-if="!isBlocked">
 <div>
 <button type="button" data-ui="profile-actions-menu-block" class="flex min-h-11 w-full items-center rounded-lg px-3 py-2 text-left text-sm font-semibold text-rose-600 hover:bg-rose-500/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw" role="menuitem" @click="confirmBlock = true">
 Block
 </button>

 <div x-show="confirmBlock" x-transition class="mt-2 rounded-lg border border-rose-400/40 bg-rose-500/5 p-3">
 <p class="text-xs">Block &#64;{{ $user->username }}?</p>
 <div class="mt-2 flex gap-2">
 <button type="button" class="btn-base btn-ghost min-h-9 px-2 py-1 text-xs" @click="confirmBlock = false">Cancel</button>
 <button type="button" class="btn-base btn-primary min-h-9 px-2 py-1 text-xs" @click="confirmBlock = false; toggleBlock(); open = false;">Confirm</button>
 </div>
 </div>
 </div>
 </template>

 <template x-if="isBlocked">
 <button type="button" class="flex min-h-11 w-full items-center rounded-lg px-3 py-2 text-left text-sm font-semibold hover:bg-emerald-500/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw" role="menuitem" @click="toggleBlock(); open = false;">
 Unblock
 </button>
 </template>

 <form method="POST" action="{{ route('users.report', ['user'=> $user]) }}">
 @csrf
 <input type="hidden" name="reason" value="other">
 <input type="hidden" name="details" value="Reported from profile actions dropdown.">
 <button type="submit" data-ui="profile-actions-menu-report" class="flex min-h-11 w-full items-center rounded-lg px-3 py-2 text-left text-sm font-semibold hover:bg-emerald-500/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw" role="menuitem">Report</button>
 </form>

 <button type="button" data-ui="profile-actions-menu-copy" class="flex min-h-11 w-full items-center rounded-lg px-3 py-2 text-left text-sm font-semibold hover:bg-emerald-500/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw" role="menuitem" @click="copyProfileUrl()">
 <span x-text="copied ? 'Copied Profile URL' : 'Copy Profile URL'">Copy Profile URL</span>
 </button>
 </div>
</div>
