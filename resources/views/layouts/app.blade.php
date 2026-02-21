@php
    $appName = config('app.name', 'LaraPets');
    $pageTitle = trim($__env->yieldContent('title'));
    $documentTitle = $pageTitle !== '' ? "{$pageTitle} · {$appName}" : $appName;
    $currentRoute = Route::currentRouteName();
    $user = Auth::user();

    $isAuthenticated = $user !== null;

    $desktopNav = [
        [
            'label' => $isAuthenticated ? 'Feed' : 'Explore Feed',
            'icon' => '🏠',
            'route' => $isAuthenticated ? 'feed.index' : 'explore.index',
            'patterns' => $isAuthenticated ? ['feed.*', 'posts.*', 'saved.*'] : ['explore.*', 'search.*', 'hashtags.*'],
        ],
        ['label' => 'Explore', 'icon' => '🧭', 'route' => 'explore.index', 'patterns' => ['explore.*', 'search.*', 'hashtags.*']],
        ['label' => 'Pets', 'icon' => '🐾', 'route' => 'pets.explore', 'patterns' => ['pets.*', 'tips.*']],
        ['label' => 'Adopt', 'icon' => '🏡', 'route' => 'pets.adopt', 'patterns' => ['pets.adopt']],
        ['label' => 'Groups', 'icon' => '👥', 'route' => 'groups.index', 'patterns' => ['groups.*']],
        ['label' => 'Events', 'icon' => '📅', 'route' => 'events.index', 'patterns' => ['events.*']],
        ['label' => 'Marketplace', 'icon' => '🛍️', 'route' => 'marketplace.index', 'patterns' => ['marketplace.*', 'messages.*']],
    ];

    $mobileNav = [
        [
            'label' => 'Home',
            'icon' => '🏠',
            'route' => $isAuthenticated ? 'feed.index' : 'explore.index',
            'patterns' => $isAuthenticated ? ['feed.*', 'posts.*'] : ['explore.*', 'search.*', 'hashtags.*'],
        ],
        ['label' => 'Explore', 'icon' => '🧭', 'route' => 'explore.index', 'patterns' => ['explore.*', 'search.*']],
        ['label' => 'Post', 'icon' => '✚', 'route' => 'posts.create', 'patterns' => ['posts.create']],
        ['label' => 'Groups', 'icon' => '👥', 'route' => 'groups.index', 'patterns' => ['groups.*']],
        ['label' => 'Profile', 'icon' => '🙂', 'route' => 'settings.profile.edit', 'patterns' => ['profile.*', 'settings.*']],
    ];

    $searchTarget = Route::has('search.index') ? route('search.index') : url('/');

    $flashMessages = collect([
        ['type' => 'success', 'message' => session('success')],
        ['type' => 'error', 'message' => session('error')],
        ['type' => 'warning', 'message' => session('warning')],
        ['type' => 'info', 'message' => session('status')],
    ])->filter(fn ($item) => filled($item['message']))->values();

    if ($errors->any()) {
        $flashMessages = $flashMessages->prepend([
            'type' => 'error',
            'message' => $errors->first(),
        ]);
    }

    $routeIsActive = static function (array $patterns) use ($currentRoute): bool {
        if (! $currentRoute) {
            return false;
        }

        foreach ($patterns as $pattern) {
            if (\Illuminate\Support\Str::is($pattern, $currentRoute)) {
                return true;
            }
        }

        return false;
    };

    $hideLeftRail = $routeIsActive([
        'profile.show',
        'profile.followers',
        'profile.following',
        'profile.edit',
        'profile.update',
        'settings.profile.*',
        'pets.show',
        'pets.edit',
        'pets.update',
        'pets.create',
    ]);

    $trendingHashtags = collect();
    $upcomingEvents = collect();
    $suggestedUsers = collect();
    $activeContests = collect();

    $communityStats = [
        ['label' => 'Members', 'value' => '--'],
        ['label' => 'Pets', 'value' => '--'],
        ['label' => 'Posts', 'value' => '--'],
    ];

    try {
        if (\Illuminate\Support\Facades\Schema::hasTable('users')) {
            $communityStats[0]['value'] = number_format((int) \App\Models\User::query()->count());
        }

        if (\Illuminate\Support\Facades\Schema::hasTable('pets')) {
            $communityStats[1]['value'] = number_format((int) \App\Models\Pet::query()->count());
        }

        if (\Illuminate\Support\Facades\Schema::hasTable('posts')) {
            $communityStats[2]['value'] = number_format((int) \App\Models\Post::query()->count());
        }

        if (\Illuminate\Support\Facades\Schema::hasTable('hashtags')) {
            $trendingHashtags = \App\Models\Hashtag::query()
                ->orderByDesc('posts_count')
                ->limit(5)
                ->get(['id', 'name', 'slug', 'posts_count']);
        }

        if (\Illuminate\Support\Facades\Schema::hasTable('events')) {
            $upcomingEvents = \App\Models\Event::query()
                ->where('start_at', '>=', now())
                ->orderBy('start_at')
                ->limit(2)
                ->get(['id', 'title', 'start_at', 'location_text', 'attendees_count']);
        }

        if ($user && \Illuminate\Support\Facades\Schema::hasTable('users')) {
            $suggestedUsers = \App\Models\User::query()
                ->whereKeyNot($user->getKey())
                ->where('is_private', false)
                ->where('is_banned', false)
                ->orderByDesc('followers_count')
                ->limit(3)
                ->get(['id', 'name', 'username', 'avatar_path', 'followers_count']);
        }

        if (\Illuminate\Support\Facades\Schema::hasTable('contests')) {
            $activeContests = \App\Models\Contest::query()
                ->whereIn('status', ['active', 'voting'])
                ->orderBy('ends_at')
                ->limit(2)
                ->get(['id', 'title', 'slug', 'status', 'ends_at', 'entries_count']);
        }
    } catch (\Throwable $exception) {
        // Keep layout resilient when schema is in flux.
    }
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        @stack('meta')

        <title>{{ $documentTitle }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=outfit:500,600,700,800|nunito-sans:400,500,600,700&display=swap" rel="stylesheet" />

        <script>
            (() => {
                const key = 'larapets-theme';
                const storedTheme = localStorage.getItem(key);
                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                const theme = storedTheme === 'light' || storedTheme === 'dark'
                    ? storedTheme
                    : (prefersDark ? 'dark' : 'light');

                document.documentElement.setAttribute('data-theme', theme);
                document.documentElement.classList.toggle('dark', theme === 'dark');
            })();
        </script>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="antialiased" x-data="appShell()">
        <div class="relative min-h-screen">
            <div class="pointer-events-none absolute inset-x-0 top-0 z-0 h-[28rem] overflow-hidden">
                <div class="absolute -left-16 top-12 h-72 w-72 rounded-full opacity-40 blur-3xl animate-float" style="background: color-mix(in srgb, var(--ui-primary) 30%, transparent);"></div>
                <div class="absolute -right-16 top-0 h-80 w-80 rounded-full opacity-35 blur-3xl animate-float" style="background: color-mix(in srgb, var(--ui-accent) 26%, transparent); animation-delay: 800ms;"></div>
            </div>

            @include('layouts.navigation', [
                'appName' => $appName,
                'searchTarget' => $searchTarget,
                'desktopNav' => $desktopNav,
                'currentRoute' => $currentRoute,
                'user' => $user,
                'routeIsActive' => $routeIsActive,
            ])

            @if ($flashMessages->isNotEmpty())
                <div class="pointer-events-none fixed inset-x-0 top-20 z-50 flex justify-center px-4 sm:justify-end">
                    <div class="pointer-events-auto w-full max-w-sm space-y-3">
                        @foreach ($flashMessages as $flash)
                            <x-flash-message :type="$flash['type']" :message="$flash['message']" />
                        @endforeach
                    </div>
                </div>
            @endif

            <div @class([
                'relative z-10 mx-auto grid w-full max-w-[1400px] grid-cols-1 gap-5 px-4 pb-24 pt-5 sm:px-6 lg:gap-6 lg:px-8 lg:pb-8',
                'lg:grid-cols-[16.5rem_minmax(0,1fr)_20rem]' => ! $hideLeftRail,
                'lg:grid-cols-[minmax(0,1fr)_20rem]' => $hideLeftRail,
            ])>
                @unless ($hideLeftRail)
                <aside class="hidden lg:block">
                    <div class="sticky top-24 space-y-4">
                        <section class="shell-panel p-4">
                            <div class="flex items-center gap-3">
                                <x-avatar :name="$user?->name ?? 'Guest User'" :src="$user?->avatar_url" size="lg" />
                                <div class="min-w-0">
                                    <p class="truncate shell-title text-base">{{ $user?->name ?? 'Guest User' }}</p>
                                    <p class="truncate text-xs shell-text-muted">{{ $user?->username ? '@'.$user->username : ($user?->email ?? 'community@larapets.test') }}</p>
                                </div>
                            </div>

                            <div class="mt-4 grid grid-cols-3 gap-2">
                                @foreach ($communityStats as $stat)
                                    <div class="rounded-xl border px-2 py-2 text-center" style="border-color: var(--ui-border); background: color-mix(in srgb, var(--ui-surface) 90%, white 10%);">
                                        <p class="shell-value text-sm">{{ $stat['value'] }}</p>
                                        <p class="text-[0.62rem] font-semibold uppercase tracking-[0.08em] shell-text-muted">{{ $stat['label'] }}</p>
                                    </div>
                                @endforeach
                            </div>
                        </section>

                        <section class="shell-card p-4">
                            <p class="shell-kicker">Navigate</p>
                            <nav class="mt-2 space-y-1" aria-label="Desktop Navigation">
                                @foreach ($desktopNav as $item)
                                    @php
                                        $href = ($item['route'] && Route::has($item['route'])) ? route($item['route']) : '#';
                                        $isActive = $routeIsActive($item['patterns'] ?? []);
                                    @endphp

                                    <a href="{{ $href }}" class="shell-nav-link {{ $isActive ? 'active' : '' }}">
                                        <span aria-hidden="true">{{ $item['icon'] }}</span>
                                        <span>{{ $item['label'] }}</span>
                                    </a>
                                @endforeach
                            </nav>
                        </section>

                        <section class="shell-card p-4">
                            <div class="mb-2 flex items-center justify-between">
                                <p class="shell-kicker">Trending Tags</p>
                                <span class="chip">Live</span>
                            </div>

                            <div class="space-y-2">
                                @forelse ($trendingHashtags as $hashtag)
                                    <a
                                        href="{{ route('hashtags.show', $hashtag) }}"
                                        class="hover-lift flex items-center justify-between rounded-xl border px-3 py-2"
                                        style="border-color: var(--ui-border);"
                                    >
                                        <span class="text-sm font-semibold" style="color: var(--ui-text);">#{{ $hashtag->name }}</span>
                                        <span class="text-xs shell-text-muted">{{ number_format((int) $hashtag->posts_count) }}</span>
                                    </a>
                                @empty
                                    <p class="text-sm shell-text-muted">No trending hashtags yet.</p>
                                @endforelse
                            </div>
                        </section>
                    </div>
                </aside>
                @endunless

                <main class="min-w-0 space-y-5">
                    @isset($header)
                        <header class="shell-card p-4 sm:p-5 animate-fade-up">
                            <div class="flex items-center justify-between gap-3">
                                <div class="min-w-0">{{ $header }}</div>
                                <span class="chip">PetSocial</span>
                            </div>
                        </header>
                    @endisset

                    <section class="space-y-5 animate-fade-up">
                        {{ $slot }}
                    </section>
                </main>

                <aside class="hidden lg:block">
                    <div class="sticky top-24 space-y-4">
                        <section class="shell-card p-4">
                            <div class="mb-2 flex items-center justify-between">
                                <p class="shell-kicker">Who To Follow</p>
                                <a href="{{ Route::has('search.index') ? route('search.index', ['type' => 'users']) : '#' }}" class="text-xs font-semibold hover:underline" style="color: var(--ui-primary);">See all</a>
                            </div>

                            <div class="space-y-2.5">
                                @forelse ($suggestedUsers as $suggested)
                                    <a href="{{ route('profile.show', ['user' => $suggested]) }}" class="hover-lift flex items-center justify-between rounded-xl border px-3 py-2" style="border-color: var(--ui-border);">
                                        <div class="flex min-w-0 items-center gap-2.5">
                                            <x-avatar :name="$suggested->name" :src="$suggested->avatar_url" size="sm" />
                                            <div class="min-w-0">
                                                <p class="truncate text-sm font-semibold" style="color: var(--ui-text);">{{ $suggested->name }}</p>
                                                <p class="truncate text-xs shell-text-muted">{{ $suggested->username ? '@'.$suggested->username : 'Pet lover' }}</p>
                                            </div>
                                        </div>
                                        <span class="text-xs shell-text-muted">{{ number_format((int) $suggested->followers_count) }}</span>
                                    </a>
                                @empty
                                    <p class="text-sm shell-text-muted">Suggestions appear after activity grows.</p>
                                @endforelse
                            </div>
                        </section>

                        <section class="shell-card p-4">
                            <div class="mb-2 flex items-center justify-between">
                                <p class="shell-kicker">Upcoming Events</p>
                                <a href="{{ Route::has('events.index') ? route('events.index') : '#' }}" class="text-xs font-semibold hover:underline" style="color: var(--ui-primary);">Browse</a>
                            </div>

                            <div class="space-y-2.5">
                                @forelse ($upcomingEvents as $event)
                                    <a href="{{ route('events.show', $event) }}" class="hover-lift block rounded-xl border px-3 py-2" style="border-color: var(--ui-border);">
                                        <p class="line-clamp-1 text-sm font-semibold" style="color: var(--ui-text);">{{ $event->title }}</p>
                                        <p class="mt-0.5 text-xs shell-text-muted">
                                            {{ optional($event->start_at)->format('M j, g:i A') ?? 'Date TBD' }}
                                            <span class="dot-divider"></span>
                                            {{ $event->location_text ?: 'Online / TBD' }}
                                        </p>
                                    </a>
                                @empty
                                    <p class="text-sm shell-text-muted">No upcoming events scheduled.</p>
                                @endforelse
                            </div>
                        </section>

                        <section class="shell-card p-4">
                            <div class="mb-2 flex items-center justify-between">
                                <p class="shell-kicker">Active Contests</p>
                                <span class="chip">{{ $activeContests->count() }}</span>
                            </div>

                            <div class="space-y-2.5">
                                @forelse ($activeContests as $contest)
                                    <a href="{{ Route::has('contests.index') ? route('contests.index') : '#' }}" class="hover-lift block rounded-xl border px-3 py-2" style="border-color: var(--ui-border);">
                                        <p class="line-clamp-1 text-sm font-semibold" style="color: var(--ui-text);">{{ $contest->title }}</p>
                                        <p class="mt-0.5 text-xs shell-text-muted">{{ ucfirst((string) $contest->status) }} · {{ number_format((int) $contest->entries_count) }} entries</p>
                                    </a>
                                @empty
                                    <p class="text-sm shell-text-muted">No active contests right now.</p>
                                @endforelse
                            </div>
                        </section>
                    </div>
                </aside>
            </div>

            <div class="fixed inset-0 z-50 lg:hidden" x-cloak x-show="mobileMenuOpen" x-transition.opacity>
                <button type="button" class="absolute inset-0 bg-slate-950/45" @click="closeMenus" aria-label="Close mobile menu"></button>

                <aside
                    class="absolute inset-y-0 left-0 flex w-72 max-w-[85vw] flex-col gap-4 border-r p-4 shadow-soft"
                    style="border-color: var(--ui-border); background: var(--ui-surface);"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="-translate-x-full"
                    x-transition:enter-end="translate-x-0"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="translate-x-0"
                    x-transition:leave-end="-translate-x-full"
                >
                    <div class="flex items-center justify-between">
                        <a href="{{ Route::has('feed.index') ? route('feed.index') : url('/') }}" class="flex items-center gap-2">
                            <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl text-lg" style="background: color-mix(in srgb, var(--ui-primary) 16%, var(--ui-surface) 84%);">🐾</span>
                            <span class="shell-title text-base">{{ $appName }}</span>
                        </a>

                        <button type="button" class="icon-button" @click="closeMenus" aria-label="Close menu">
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M5 5l10 10M15 5L5 15" stroke-linecap="round" />
                            </svg>
                        </button>
                    </div>

                    <x-search-form :action="$searchTarget" class="w-full" placeholder="Search on {{ $appName }}" />

                    <nav class="space-y-1" aria-label="Mobile Navigation Drawer">
                        @foreach ($desktopNav as $item)
                            @php
                                $href = ($item['route'] && Route::has($item['route'])) ? route($item['route']) : '#';
                                $isActive = $routeIsActive($item['patterns'] ?? []);
                            @endphp

                            <a href="{{ $href }}" class="shell-nav-link {{ $isActive ? 'active' : '' }}" @click="closeMenus">
                                <span aria-hidden="true">{{ $item['icon'] }}</span>
                                <span>{{ $item['label'] }}</span>
                            </a>
                        @endforeach
                    </nav>
                </aside>
            </div>

            <nav class="fixed inset-x-3 bottom-3 z-40 lg:hidden">
                <div class="shell-card flex items-center justify-between px-2 py-1.5">
                    @foreach ($mobileNav as $item)
                        @php
                            $href = ($item['route'] && Route::has($item['route'])) ? route($item['route']) : '#';
                            $isActive = $routeIsActive($item['patterns'] ?? []);
                            $isPrimaryAction = ($item['route'] ?? null) === 'posts.create';
                        @endphp

                        <a
                            href="{{ $href }}"
                            class="flex min-w-0 flex-1 flex-col items-center gap-0.5 rounded-xl px-1 py-1 text-[0.65rem] font-semibold"
                            style="color: {{ $isPrimaryAction ? '#f8fafc' : ($isActive ? 'var(--ui-primary)' : 'var(--ui-text-muted)') }}; background: {{ $isPrimaryAction ? 'linear-gradient(135deg, var(--ui-primary), var(--ui-primary-strong))' : 'transparent' }};"
                        >
                            <span class="text-base" aria-hidden="true">{{ $item['icon'] }}</span>
                            <span class="truncate">{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                </div>
            </nav>
        </div>
    </body>
</html>
