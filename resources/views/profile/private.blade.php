@section('title','@'.$user->username.'— Private Profile')

@push('meta')
 <meta name="robots" content="noindex, nofollow">
@endpush

<x-app-layout>
 <div class="w-full min-w-0 space-y-5" data-ui="private-profile-shell">
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

 <div class="px-6 pb-6 pt-14 lg:pt-4">
 <div class="flex flex-col gap-1 lg:pl-36">
 <div class="pb-1">
 <div class="flex flex-wrap items-center gap-2">
 <h1 id="private-profile-header-title" class="text-2xl font-bold font-display text-bark">{{ $user->name }}</h1>
 @if ($user->profile_verified)
 <x-ui.verified-badge tooltip-id="private-profile-header-verified-tooltip"/>
 @endif
 </div>
 <p class="text-sm text-fur">&#64;{{ $user->username }}</p>
 </div>
 </div>
 </div>
 </section>

 <x-ui.card>
 <x-ui.empty-state icon="🔒"
 title="This account is private"
 :description="($profileVisibility ?? 'private') === 'private'
 ? 'This profile is not available publicly.'
 : 'This profile is private and followers-only. Follow @'.$user->username.' to see posts, photos, and pet profiles.'">
 @auth
 <x-slot name="action">
 @if (($profileVisibility ?? 'private') !== 'private')
 <x-follow-button :user="$user" :follow-status="($followStatus ?? 'none')" size="lg"/>
 @endif
 </x-slot>
 @else
 <x-slot name="action">
 <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
 @if (Route::has('register'))
 <x-ui.button :href="route('register')" variant="primary" size="sm" class="min-h-11">Join PetSocial</x-ui.button>
 @endif
 <x-ui.button :href="route('login')" variant="outline" size="sm" class="min-h-11">Log In</x-ui.button>
 </div>
 </x-slot>
 @endauth
 </x-ui.empty-state>
 </x-ui.card>
 </div>
</x-app-layout>
