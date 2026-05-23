@php
 $privateDisplayName = $user->display_name ?: $user->name;
 $privateProfileVisibility = $profileVisibility ?? 'private';
 $privateFollowStatus = $followStatus ?? 'none';
 $privateProfileUrl = $user->profile_url;
 $privateMessageUrl = (($canMessage ?? false) && Route::has('messages.conversation')) ? route('messages.conversation', ['peer'=> $user]) : false;
@endphp

@section('title','@'.$user->username.'— Private Profile')

@push('meta')
 <meta name="robots" content="noindex, nofollow">
@endpush

<x-app-layout>
 <div class="w-full min-w-0 space-y-5" data-ui="private-profile-shell" x-data="profileActions({
 followStatus: @js($privateFollowStatus),
 isFollowing: @js($privateFollowStatus === 'following'),
 isBlocked: false,
 isBlockedBy: false,
 followersCount: @js((int) ($user->followers_count ?? 0)),
 followUrl: @js(route('users.follow', ['user'=> $user])),
 unfollowUrl: @js(route('users.unfollow', ['user'=> $user])),
 blockUrl: @js(route('users.block', ['user'=> $user])),
 unblockUrl: @js(route('users.unblock', ['user'=> $user]))
 })">
 <section class="w-full min-w-0 overflow-hidden rounded-[var(--radius-card)] border border-whisker/40 bg-warm-white shadow-card" data-ui="private-profile-hero" data-profile-section="profile-header" aria-labelledby="private-profile-header-title">
 <div data-ui="private-profile-hero-frame" class="relative">
 <div data-ui="private-profile-cover-banner" class="relative h-[140px] w-full overflow-hidden md:h-[180px] lg:h-[280px]">
 <div data-ui="private-profile-cover-fallback" class="absolute inset-0 {{ $user->profile_default_gradient }}"></div>
 <div class="absolute inset-0 bg-bark/35"></div>
 </div>

 <div data-ui="private-profile-avatar" class="absolute left-6 -bottom-[45px] z-10 flex h-[90px] w-[90px] items-center justify-center overflow-hidden rounded-full border-4 border-white bg-warm-white shadow-card lg:-bottom-[60px] lg:h-[120px] lg:w-[120px]">
 @if ($user->avatar_url)
 <img data-ui="profile-avatar-image" src="{{ $user->avatar_url }}" alt="{{ $user->name }} profile avatar" class="h-full w-full object-cover" loading="lazy">
 @else
 <div data-ui="profile-avatar-initial" class="flex h-full w-full items-center justify-center {{ $user->profile_default_avatar_color }}" role="img" aria-label="{{ $user->name }} generated avatar">
 <span class="font-display text-4xl font-bold uppercase lg:text-5xl" aria-hidden="true">{{ $user->profile_initial }}</span>
 </div>
 @endif
 </div>
 </div>

 <div class="px-6 pb-6 pt-14 lg:pt-16">
 <div class="flex flex-col gap-1">
 <div class="pb-1">
 <div class="flex flex-wrap items-center gap-2">
 <h1 id="private-profile-header-title" data-ui="profile-display-name" class="text-2xl font-bold font-display text-bark">{{ $privateDisplayName }}</h1>
 @if ($user->profile_verified)
 <x-ui.verified-badge tooltip-id="private-profile-header-verified-tooltip"/>
 @endif
 </div>
 <p data-ui="profile-username" class="text-sm font-medium text-fur">&#64;{{ $user->username }}</p>
 </div>
 </div>
 </div>
 </section>

 <x-ui.card>
 <x-ui.empty-state icon="🔒"
 title="This account is private"
 :description="$privateProfileVisibility === 'private'
 ? 'This profile is not available publicly.'
 : 'This profile is private and followers-only. Follow @'.$user->username.' to see posts, photos, and pet profiles.'">
 @auth
 <x-slot name="action">
 @if ($privateProfileVisibility !== 'private')
 <div class="flex flex-col items-center gap-2 sm:flex-row sm:justify-center" data-ui="private-profile-actions">
 <div class="relative" x-data="{ confirmWithdraw: false }" @click.outside="confirmWithdraw = false" @keydown.escape.window="confirmWithdraw = false">
 <button
 type="button"
 data-ui="profile-follow-primary-action"
 data-follow-status="{{ $privateFollowStatus }}"
 x-bind:data-requested="followStatus === 'pending'"
 class="inline-flex min-h-11 min-w-[10rem] items-center justify-center rounded-[var(--radius-control)] px-5 py-2 text-sm font-semibold transition-all duration-150 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw disabled:cursor-not-allowed disabled:opacity-60"
 :class="followButtonClass"
 x-bind:disabled="busy || hasBlockingRelationship"
 x-bind:aria-pressed="(followStatus === 'following').toString()"
 aria-haspopup="menu"
 aria-controls="profile-withdraw-request-dropdown"
 x-bind:aria-expanded="(confirmWithdraw && followStatus === 'pending').toString()"
 x-bind:aria-label="followStatus === 'following' ? @js('Unfollow '.$user->name) : (followStatus === 'pending' ? @js('Requested to follow '.$user->name) : @js('Request to Follow '.$user->name))"
 @click="if (followStatus === 'pending') { confirmWithdraw = ! confirmWithdraw; return; } toggleFollow()">
 <span x-text="busy ? 'Saving...' : (followStatus === 'none' ? 'Request to Follow' : followLabel)">{{ $privateFollowStatus === 'pending' ? 'Requested' : 'Request to Follow' }}</span>
 </button>

 <div
 id="profile-withdraw-request-dropdown"
 x-show="confirmWithdraw && followStatus === 'pending'"
 x-cloak
 x-transition
 data-ui="profile-withdraw-request-dropdown"
 class="absolute left-1/2 z-20 mt-2 w-56 -translate-x-1/2 rounded-xl border border-[var(--ui-border)] bg-[color:var(--ui-surface)] p-3 text-left shadow-lg"
 role="menu"
 aria-label="Pending follow request options"
 >
 <p class="text-xs font-semibold text-bark">Withdraw follow request?</p>
 <div class="mt-2 grid gap-1">
 <button type="button" data-ui="profile-withdraw-request-action" class="flex min-h-10 w-full items-center rounded-lg px-3 py-2 text-left text-sm font-semibold text-rose-600 hover:bg-rose-500/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw" role="menuitem" @click="confirmWithdraw = false; cancelRequest()">
 Withdraw Request
 </button>
 <button type="button" data-ui="profile-keep-request-action" class="flex min-h-10 w-full items-center rounded-lg px-3 py-2 text-left text-sm font-semibold hover:bg-emerald-500/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw" role="menuitem" @click="confirmWithdraw = false">
 Keep Request
 </button>
 </div>
 </div>
 </div>

 @include('profile._actions-dropdown', ['user'=> $user,'isBlocked'=> false,'profileUrl'=> $privateProfileUrl,'messageUrl'=> $privateMessageUrl])
 </div>
 @endif
 </x-slot>
 @else
 <x-slot name="action">
 <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
 @if (Route::has('profile.guest-follow'))
 <form method="POST" action="{{ route('profile.guest-follow', ['user'=> $user]) }}" data-ui="private-profile-guest-follow-form" class="w-full sm:w-auto">
 @csrf
 <x-ui.button type="submit" variant="primary" size="sm" class="min-h-11 sm:min-w-28" data-ui="private-profile-guest-follow-action">Follow</x-ui.button>
 </form>
 @endif
 @if (Route::has('login'))
 <x-ui.button :href="route('login')" variant="outline" size="sm" class="min-h-11">Log In</x-ui.button>
 @endif
 </div>
 </x-slot>
 @endauth
 </x-ui.empty-state>
 </x-ui.card>

 @auth
 @if ((int) auth()->id() !== (int) $user->getKey())
 <livewire:profile.report-modal
 :profile-user-id="$user->getKey()"
 wire:key="private-profile-report-modal-{{ $user->getKey() }}"
 />
 @endif
 @endauth
 </div>
</x-app-layout>
