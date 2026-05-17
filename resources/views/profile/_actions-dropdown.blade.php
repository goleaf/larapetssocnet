@props([
'user',
'isBlocked'=> false,
])

<div class="relative" x-data="{ open: false, confirmBlock: false }" @click.outside="open = false" @keydown.escape.window="open = false">
 <button
 type="button"
 class="btn-base btn-ghost min-h-11 px-3 py-2 text-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw"
 aria-label="Profile actions"
 @click="open = !open"
 >
 •••
 </button>

 <div
 x-show="open"
 x-transition
 class="absolute right-0 z-20 mt-2 w-56 rounded-xl border border-[var(--ui-border)] bg-[color:var(--ui-surface)] p-2 shadow-lg"
 role="menu"
 aria-label="Profile action menu"
 >
 <a href="{{ route('profile.show', ['user'=> $user]) }}" class="flex min-h-11 items-center rounded-lg px-3 py-2 text-sm font-semibold hover:bg-emerald-500/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw" role="menuitem">Copy profile link</a>
 <form method="POST" action="{{ route('users.report', ['user'=> $user]) }}">
 @csrf
 <input type="hidden" name="reason" value="other">
 <input type="hidden" name="details" value="Reported from profile actions dropdown.">
 <button type="submit" class="flex min-h-11 w-full items-center rounded-lg px-3 py-2 text-left text-sm font-semibold hover:bg-emerald-500/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw" role="menuitem">Report user</button>
 </form>

 <div class="my-1 border-t border-[var(--ui-border)]"></div>

 <template x-if="!isBlocked">
 <div>
 <button type="button" class="flex min-h-11 w-full items-center rounded-lg px-3 py-2 text-left text-sm font-semibold text-rose-600 hover:bg-rose-500/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw" role="menuitem" @click="confirmBlock = true">
 Block user
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
 Unblock user
 </button>
 </template>
 </div>
</div>
