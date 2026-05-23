<!DOCTYPE html>
<html lang="{{ str_replace('_','-', app()->getLocale()) }}">
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

 @vite(['resources/scss/app.scss', 'resources/js/app.js'])
 </head>
 <body class="min-h-screen bg-cream font-body text-bark antialiased" x-data="appShell()">
 <div class="relative min-h-screen">
 <div class="pointer-events-none absolute inset-x-0 top-0 z-0 h-32 border-b border-[var(--border-soft)] bg-[color:var(--surface-page)]"></div>

 <!-- New Navbar Component -->
 <x-ui.navbar />

 <!-- New Flash Messages & Toast Container -->
 <div class="ui-container mt-4">
 <x-ui.flash-messages />
 </div>
 <x-ui.toast-container />
 <x-ui.confirm-modal />
 @auth
 <livewire:pets.create-wizard />
 @endauth

 <div @class([
'relative z-10 mx-auto grid w-full max-w-[var(--container-max)] grid-cols-1 gap-5 px-4 pb-8 pt-2 sm:px-6 lg:gap-6 lg:px-6',
'lg:grid-cols-[16.5rem_minmax(0,1fr)]'=> ! $hideLeftRail,
'lg:grid-cols-[minmax(0,1fr)]'=> $hideLeftRail,
 ])>
 @unless ($hideLeftRail)
 <aside class="hidden lg:block" data-ui="app-left-rail">
 <div class="sticky top-24 max-h-[calc(100dvh-7rem)] space-y-4 overflow-y-auto overscroll-contain pb-4 pr-1 [scrollbar-gutter:stable]">
 <x-ui.card>
 <div class="flex items-center gap-3">
 <x-ui.avatar :name="$user?->name ??'Guest User'" :src="$user?->avatar_url" :user="$user" size="lg"/>
 <div class="min-w-0">
 <p class="truncate text-base font-semibold text-bark">{{ $user?->name ??'Guest User'}}</p>
 <p class="truncate text-xs text-fur">{{ $user?->username ?'@'.$user->username : ($user?->email ??'community@larapets.test') }}</p>
 </div>
 </div>

 <div class="mt-4 grid grid-cols-3 gap-2">
 @foreach ($communityStats as $stat)
 <div class="ui-list-item px-2 py-2 text-center">
 <p class="text-sm font-bold text-bark">{{ $stat['value'] }}</p>
 <p class="text-xs font-semibold uppercase tracking-[0.08em] text-fur">{{ $stat['label'] }}</p>
 </div>
 @endforeach
 </div>
 </x-ui.card>

 <x-ui.card>
 <h4 class="px-1 text-xs font-bold font-mono uppercase tracking-wider text-fur mb-2">Navigate</h4>
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
 :user="$suggestedUser"
 >
 <x-slot name="avatar">
 <x-ui.avatar :src="$suggestedUser->avatar_url" :name="$suggestedUser->name" :user="$suggestedUser" size="sm"/>
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
 <h4 class="text-xs font-bold font-mono uppercase tracking-wider text-fur">Who To Follow</h4>
 <a
 href="{{ Route::has('search.index') ? route('search.index', ['type'=>'users']) :'#'}}"
 class="ui-link text-xs font-semibold"
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
 :user="$suggested"
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
 <h4 class="text-xs font-bold font-mono uppercase tracking-wider text-fur">Upcoming Events</h4>
 <a
 href="{{ Route::has('events.index') ? route('events.index') :'#'}}"
 class="ui-link text-xs font-semibold"
 >
 Browse
 </a>
 </div>

 <div class="space-y-2 mt-3">
 @forelse ($upcomingEvents as $event)
 <a
 href="{{ route('events.show', $event) }}"
 class="ui-list-item block px-3 py-2"
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
 <h4 class="text-xs font-bold font-mono uppercase tracking-wider text-fur">Active Contests</h4>
 <x-ui.badge variant="success" size="sm">{{ $activeContests->count() }}</x-ui.badge>
 </div>

 <div class="space-y-2 mt-3">
 @forelse ($activeContests as $contest)
 <a
 href="{{ Route::has('contests.index') ? route('contests.index') :'#'}}"
 class="ui-list-item block px-3 py-2"
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
 <h4 class="text-xs font-bold font-mono uppercase tracking-wider text-fur">Trending Tags</h4>
 <x-ui.badge variant="success" size="sm">Live</x-ui.badge>
 </div>

 <div class="space-y-2 mt-3">
 @forelse ($trendingHashtags as $hashtag)
 <a
 href="{{ route('hashtags.show', $hashtag) }}"
 class="ui-list-item flex items-center justify-between px-3 py-2"
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
 <nav class="lg:hidden" aria-label="Mobile navigation">
 <div class="shell-card grid grid-cols-5 gap-1 px-2 py-2">
 @foreach ($mobileNav as $item)
 <a
 href="{{ $item['href'] }}"
 class="flex min-h-12 min-w-0 flex-col items-center justify-center gap-1 rounded-[var(--radius-soft)] px-1 py-1 text-xs font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw {{ $item['isPrimaryAction'] ? 'bg-paw text-[color:var(--accent-on)] hover:bg-paw-dark' : ($item['active'] ? 'bg-paw-light text-paw-dark' : 'text-fur hover:bg-cream hover:text-bark') }}"
 @if($item['active']) aria-current="page" @endif
 >
 <span class="text-base leading-none" aria-hidden="true">{{ $item['icon'] }}</span>
 <span class="truncate">{{ $item['label'] }}</span>
 </a>
 @endforeach
 </div>
 </nav>

 @isset($header)
 <div class="animate-fade-up">
 {{ $header }}
 </div>
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
 </div>
 </body>
</html>
