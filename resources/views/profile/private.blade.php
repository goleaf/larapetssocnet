@section('title','@'.$user->username.'— Private Profile')

@push('meta')
 <meta name="robots" content="noindex, nofollow">
@endpush

<x-app-layout>
 <div class="mx-auto max-w-3xl space-y-5">
 <section class="overflow-hidden rounded-2xl border border-whisker/40 bg-warm-white shadow-card">
 <div class="h-40 w-full bg-gradient-to-r from-paw-light via-cream to-sky-light"></div>

 <div class="px-6 pb-6">
 <div class="-mt-12 flex items-end gap-4">
 <x-ui.avatar :src="$user->avatar_url" :name="$user->name" size="2xl" class="h-24 w-24 border-4 border-warm-white bg-warm-white shadow-xl"/>

 <div class="pb-1">
 <h1 class="text-2xl font-bold font-display text-bark">{{ $user->name }}</h1>
 <p class="text-sm text-fur">&#64;{{ $user->username }}</p>
 </div>
 </div>
 </div>
 </section>

 <x-ui.card>
 <x-ui.empty-state icon="🔒"
 title="This account is private"
 :description="($profileVisibility ?? 'private') === 'private'
 ? 'Only you can view this profile.'
 : 'This profile is private and followers-only. Follow @'.$user->username.' to see posts, photos, and pet profiles.'">
 @auth
 <x-slot name="action">
 @if (($profileVisibility ?? 'private') !== 'private')
 <x-follow-button :user="$user" :follow-status="($followStatus ?? 'none')" size="lg"/>
 @endif
 </x-slot>
 @else
 <x-slot name="action">
 <x-ui.button :href="route('login')" variant="primary" size="sm">Log In to Follow</x-ui.button>
 </x-slot>
 @endauth
 </x-ui.empty-state>
 </x-ui.card>
 </div>
</x-app-layout>
