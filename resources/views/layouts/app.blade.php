<!DOCTYPE html>
<html lang="{{ str_replace('_','-', app()->getLocale()) }}" data-theme="petssocnet">
 <head>
 <meta charset="utf-8">
 <meta name="viewport" content="width=device-width, initial-scale=1">
 <meta name="csrf-token" content="{{ csrf_token() }}">
 @hasStack('meta')
 @stack('meta')
 @else
 <meta name="description" content="PetSocial is a community for sharing pet moments, care tips, and adoption stories.">
 @endif

 <title>{{ trim((string) ($title ?? $__env->yieldContent('title'))) !== '' ? trim((string) ($title ?? $__env->yieldContent('title'))).' · '.($appName ?? config('app.name', 'LaraPets')) : ($appName ?? config('app.name', 'LaraPets')) }}</title>

 <link rel="preconnect" href="https://fonts.bunny.net">
 <link href="https://fonts.bunny.net/css?family=outfit:500,600,700,800|nunito-sans:400,500,600,700&display=swap" rel="stylesheet"/>

 <livewire:styles />
 @vite(['resources/scss/app.scss', 'resources/js/app.js'])
 </head>
 <body class="min-h-screen bg-cream font-body text-bark antialiased" x-data="appShell()">
 <div class="relative min-h-screen">
 <!-- Background blobs -->
 <div class="pointer-events-none absolute inset-x-0 top-0 z-0 h-[28rem] overflow-hidden">
 <div class="absolute -left-16 top-12 h-72 w-72 rounded-full opacity-40 blur-3xl animate-float bg-paw-light"></div>
 <div class="absolute -right-16 top-0 h-80 w-80 rounded-full opacity-35 blur-3xl animate-float bg-amber-light" style="animation-delay: 800ms;"></div>
 </div>

 <!-- New Navbar Component -->
 <x-ui.navbar />

 <!-- New Flash Messages & Toast Container -->
 <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4">
 <x-ui.flash-messages />
 </div>
 <x-ui.toast-container />
 <x-ui.confirm-modal />

 <div @class([
'relative z-10 mx-auto grid w-full max-w-7xl grid-cols-1 gap-5 px-4 pb-24 pt-2 sm:px-6 lg:gap-6 lg:px-8 lg:pb-8',
'lg:grid-cols-[16.5rem_minmax(0,1fr)]'=> ! $hideLeftRail,
'lg:grid-cols-[minmax(0,1fr)]'=> $hideLeftRail,
 ])>
 @unless ($hideLeftRail)
 <aside class="hidden lg:block">
 <div class="sticky top-24 space-y-4">
 <x-ui.card>
 <div class="flex items-center gap-3">
 <x-ui.avatar :name="$user?->name ??'Guest User'" :src="$user?->avatar_url" size="lg"/>
 <div class="min-w-0">
 <p class="truncate text-base font-semibold text-bark">{{ $user?->name ??'Guest User'}}</p>
 <p class="truncate text-xs text-fur">{{ $user?->username ?'@'.$user->username : ($user?->email ??'community@larapets.test') }}</p>
 </div>
 </div>

 <div class="mt-4 grid grid-cols-3 gap-2">
 @foreach ($communityStats as $stat)
 <div class="rounded-xl border border-whisker/30 bg-warm-white px-2 py-2 text-center">
 <p class="text-sm font-bold text-bark">{{ $stat['value'] }}</p>
 <p class="text-[0.62rem] font-semibold uppercase tracking-[0.08em] text-fur">{{ $stat['label'] }}</p>
 </div>
 @endforeach
 </div>
 </x-ui.card>

 <x-ui.card>
 <h4 class="px-1 text-xs font-bold font-display uppercase tracking-wider text-fur mb-2">Navigate</h4>
 <x-ui.sidebar-nav :items="$desktopNav" class="!mb-0"/>
 </x-ui.card>

 <x-ui.card>
 <x-slot name="header">
 <x-ui.card-header title="Your Groups" subtitle="Communities you are active in">
 <x-slot name="action">
 <x-ui.button href="{{ route('groups.index', ['privacy'=>'joined']) }}" variant="ghost" size="xs">
 Browse
 </x-ui.button>
 </x-slot>
 </x-ui.card-header>
 </x-slot>

 <div class="space-y-1 -mx-2">
 @forelse ($yourGroups as $group)
 <x-ui.user-row
 :name="$group->name"
 :subtitle="\Illuminate\Support\Str::headline((string) ($group->privacy ??'public'))"
 :href="route('groups.show', filled((string) ($group->slug ?? '')) ? $group->slug : $group->id)"
 class="px-2"
 >
 <x-slot name="action">
 <span class="text-xs text-fur">
 {{ number_format((int) ($group->members_count ?? 0)) }}
 </span>
 </x-slot>
 </x-ui.user-row>
 @empty
 <p class="px-2 text-sm text-fur">
 You have not joined any groups yet.
 </p>
 @endforelse
 </div>

 <div class="mt-4">
 <x-ui.button href="{{ route('groups.create') }}" variant="primary" full>
 Create a Group
 </x-ui.button>
 </div>
 </x-ui.card>

 <x-ui.card>
 <x-slot name="header">
 <x-ui.card-header title="Suggested People" subtitle="Grow your pet network"/>
 </x-slot>

 <div class="space-y-2">
 @forelse ($suggestedUsers as $suggestedUser)
 <x-ui.user-row
 :name="$suggestedUser->name"
 :subtitle="$suggestedUser->username ?'@'.$suggestedUser->username :'Pet lover'"
 :href="route('profile.show', $suggestedUser)"
 >
 <x-slot name="avatar">
 <x-ui.avatar :src="$suggestedUser->avatar_url" :name="$suggestedUser->name" size="sm"/>
 </x-slot>
 </x-ui.user-row>
 @empty
 <p class="text-sm text-fur">
 Suggestions refresh as more members join.
 </p>
 @endforelse
 </div>
 </x-ui.card>

 <x-ui.card>
 <div class="mb-3 flex items-center justify-between">
 <h4 class="text-xs font-bold font-display uppercase tracking-wider text-fur">Who To Follow</h4>
 <a
 href="{{ Route::has('search.index') ? route('search.index', ['type'=>'users']) :'#'}}"
 class="text-xs font-semibold hover:underline text-paw"
 >
 See all
 </a>
 </div>

 <div class="space-y-1 -mx-2">
 @forelse ($suggestedUsers as $suggested)
 <x-ui.user-row 
 :name="$suggested->name"
 :subtitle="$suggested->username ?'@'.$suggested->username :'Pet lover'"
 :avatar="$suggested->avatar_url"
 :href="route('profile.show', ['user'=> $suggested])"
 class="px-2"
 >
 <x-slot name="action">
 <span class="text-xs text-fur">{{ number_format((int) $suggested->followers_count) }}</span>
 </x-slot>
 </x-ui.user-row>
 @empty
 <p class="text-sm text-fur px-2">Suggestions appear after activity grows.</p>
 @endforelse
 </div>
 </x-ui.card>

 <x-ui.card>
 <div class="mb-3 flex items-center justify-between">
 <h4 class="text-xs font-bold font-display uppercase tracking-wider text-fur">Upcoming Events</h4>
 <a
 href="{{ Route::has('events.index') ? route('events.index') :'#'}}"
 class="text-xs font-semibold hover:underline text-paw"
 >
 Browse
 </a>
 </div>

 <div class="space-y-2 mt-3">
 @forelse ($upcomingEvents as $event)
 <a
 href="{{ route('events.show', $event) }}"
 class="block rounded-xl border border-whisker/30 bg-warm-white px-3 py-2 hover:bg-cream transition-colors shadow-sm"
 >
 <p class="line-clamp-1 text-sm font-semibold text-bark">{{ $event->title }}</p>
 <p class="mt-0.5 text-xs text-fur">
 {{ optional($event->start_at)->format('M j, g:i A') ??'Date TBD'}}
 <span class="mx-1">•</span>
 {{ $event->location_text ?:'Online / TBD'}}
 </p>
 </a>
 @empty
 <p class="text-sm text-fur">No upcoming events scheduled.</p>
 @endforelse
 </div>
 </x-ui.card>

 <x-ui.card>
 <div class="mb-3 flex items-center justify-between">
 <h4 class="text-xs font-bold font-display uppercase tracking-wider text-fur">Active Contests</h4>
 <x-ui.badge variant="success" size="sm">{{ $activeContests->count() }}</x-ui.badge>
 </div>

 <div class="space-y-2 mt-3">
 @forelse ($activeContests as $contest)
 <a
 href="{{ Route::has('contests.index') ? route('contests.index') :'#'}}"
 class="block rounded-xl border border-whisker/30 bg-warm-white px-3 py-2 hover:bg-cream transition-colors shadow-sm"
 >
 <p class="line-clamp-1 text-sm font-semibold text-bark">{{ $contest->title }}</p>
 <p class="mt-0.5 text-xs text-fur">
 {{ ucfirst((string) $contest->status) }}
 ·
 {{ number_format((int) $contest->entries_count) }} entries
 </p>
 </a>
 @empty
 <p class="text-sm text-fur">No active contests right now.</p>
 @endforelse
 </div>
 </x-ui.card>

 <x-ui.card>
 <div class="mb-2 flex items-center justify-between">
 <h4 class="text-xs font-bold font-display uppercase tracking-wider text-fur">Trending Tags</h4>
 <x-ui.badge variant="success" size="sm">Live</x-ui.badge>
 </div>

 <div class="space-y-2 mt-3">
 @forelse ($trendingHashtags as $hashtag)
 <a
 href="{{ route('hashtags.show', $hashtag) }}"
 class="flex items-center justify-between rounded-xl border border-whisker/30 bg-warm-white px-3 py-2 hover:bg-cream transition-colors shadow-sm"
 >
 <span class="text-sm font-semibold text-bark">#{{ $hashtag->name }}</span>
 <span class="text-xs text-fur">{{ number_format((int) $hashtag->posts_count) }}</span>
 </a>
 @empty
 <p class="text-sm text-fur">No trending hashtags yet.</p>
 @endforelse
 </div>
 </x-ui.card>
 </div>
 </aside>
 @endunless

 <main class="min-w-0 space-y-5">
 @isset($header)
 <x-ui.card class="animate-fade-up">
 <div class="flex items-center justify-between gap-3">
 <div class="min-w-0">{{ $header }}</div>
 <x-ui.badge variant="primary" size="sm">PetSocial</x-ui.badge>
 </div>
 </x-ui.card>
 @endisset

 <section class="space-y-5 animate-fade-up">
 @isset($slot)
 {{ $slot }}
 @endisset
 @hasSection('content')
 @yield('content')
 @endif
 </section>
 </main>
 </div>
 
 <!-- Mobile Bottom Nav -->
 <nav class="fixed inset-x-3 bottom-3 z-40 lg:hidden">
 <div class="bg-warm-white rounded-2xl shadow-card-hover border border-whisker/30 flex items-center justify-between px-2 py-1.5">
 @foreach ($mobileNav as $item)
 <a
 href="{{ $item['href'] }}"
 class="flex min-w-0 flex-1 flex-col items-center gap-0.5 rounded-xl px-1 py-1 text-[0.65rem] font-semibold transition-colors {{ $item['isPrimaryAction'] ? 'bg-paw text-white shadow-button hover:bg-paw-dark' : ($item['active'] ? 'text-paw' : 'text-fur hover:bg-cream hover:text-bark') }}"
 >
 <span class="text-base" aria-hidden="true">{{ $item['icon'] }}</span>
 <span class="truncate">{{ $item['label'] }}</span>
 </a>
 @endforeach
 </div>
 </nav>
 </div>
 <livewire:scripts />
 </body>
</html>
