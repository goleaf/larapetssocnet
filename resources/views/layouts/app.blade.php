@php
    $appName = config('app.name', 'LaraPets');
    $currentRoute = Route::currentRouteName();
    $user = Auth::user();

    $desktopNav = [
        ['label' => 'Feed', 'icon' => '🏠', 'route' => 'dashboard', 'href' => null],
        ['label' => 'Pets', 'icon' => '🐾', 'route' => null, 'href' => '#'],
        ['label' => 'Groups', 'icon' => '👥', 'route' => null, 'href' => '#'],
        ['label' => 'Events', 'icon' => '📅', 'route' => null, 'href' => '#'],
        ['label' => 'Marketplace', 'icon' => '🛍️', 'route' => null, 'href' => '#'],
    ];

    $mobileNav = [
        ['label' => 'Home', 'icon' => '🏠', 'route' => 'dashboard', 'href' => null],
        ['label' => 'Pets', 'icon' => '🐾', 'route' => null, 'href' => '#'],
        ['label' => 'Groups', 'icon' => '👥', 'route' => null, 'href' => '#'],
        ['label' => 'Events', 'icon' => '📅', 'route' => null, 'href' => '#'],
        ['label' => 'Profile', 'icon' => '🙂', 'route' => 'profile.edit', 'href' => null],
    ];

    $searchTarget = Route::has('dashboard') ? route('dashboard') : url('/');

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

    $leftStats = [
        ['label' => 'Pets Nearby', 'value' => '54'],
        ['label' => 'Open Adoptions', 'value' => '12'],
        ['label' => 'Active Groups', 'value' => '8'],
    ];
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $appName }}</title>

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
            @include('layouts.navigation', [
                'appName' => $appName,
                'searchTarget' => $searchTarget,
                'desktopNav' => $desktopNav,
                'currentRoute' => $currentRoute,
                'user' => $user,
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

            <div class="mx-auto grid w-full max-w-7xl grid-cols-1 gap-5 px-4 pb-24 pt-5 sm:px-6 lg:grid-cols-[16rem_minmax(0,1fr)_19rem] lg:gap-6 lg:px-8 lg:pb-8">
                <aside class="hidden lg:block">
                    <div class="shell-card sticky top-24 space-y-5 p-4">
                        <div class="flex items-center gap-3">
                            <x-avatar :name="$user?->name ?? 'Guest User'" :src="$user?->avatar_url" size="lg" />
                            <div class="min-w-0">
                                <p class="truncate shell-title text-sm">{{ $user?->name ?? 'Guest User' }}</p>
                                <p class="truncate text-xs shell-text-muted">{{ $user?->email ?? 'community@larapets.test' }}</p>
                            </div>
                        </div>

                        <nav class="space-y-1" aria-label="Desktop Navigation">
                            @foreach ($desktopNav as $item)
                                @php
                                    $href = ($item['route'] && Route::has($item['route']))
                                        ? route($item['route'])
                                        : ($item['href'] ?? '#');
                                    $isActive = $item['route'] && $currentRoute
                                        ? str_starts_with($currentRoute, $item['route'])
                                        : false;
                                @endphp

                                <a href="{{ $href }}" class="shell-nav-link {{ $isActive ? 'active' : '' }}">
                                    <span aria-hidden="true">{{ $item['icon'] }}</span>
                                    <span>{{ $item['label'] }}</span>
                                </a>
                            @endforeach
                        </nav>

                        <div class="space-y-2">
                            <p class="text-xs font-semibold uppercase tracking-[0.08em] shell-text-muted">Community Snapshot</p>
                            <div class="grid gap-2">
                                @foreach ($leftStats as $stat)
                                    <div class="shell-card-muted flex items-center justify-between px-3 py-2 text-sm">
                                        <span class="shell-text-muted">{{ $stat['label'] }}</span>
                                        <span class="font-bold" style="color: var(--ui-primary)">{{ $stat['value'] }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </aside>

                <main class="min-w-0 space-y-5">
                    @isset($header)
                        <header class="shell-card p-4 sm:p-5">
                            <div class="flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    {{ $header }}
                                </div>
                                <span class="chip">Wave 1 UI</span>
                            </div>
                        </header>
                    @endisset

                    <section class="space-y-5">
                        {{ $slot }}
                    </section>
                </main>

                <aside class="hidden lg:block">
                    <div class="sticky top-24 space-y-4">
                        <x-user-card
                            name="Mia Parker"
                            headline="Volunteer · Downtown Shelter"
                            bio="Coordinates weekend walks and adoption spotlights for senior dogs."
                            followers="842"
                            :following="true"
                        />

                        <x-group-card
                            name="Weekend Dog Walkers"
                            description="Friendly morning walks, route updates, and foster tips."
                            members="128"
                            privacy="Public"
                            cta-label="Join Group"
                            cta-href="#"
                        />

                        <x-event-card
                            title="Adoption Picnic"
                            starts-at="Sat · 10:00 AM"
                            location="Riverfront Park"
                            host="LaraPets Community"
                            attendees="36"
                            cta-label="RSVP"
                            cta-href="#"
                        />
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
                        <a href="{{ Route::has('dashboard') ? route('dashboard') : url('/') }}" class="flex items-center gap-2">
                            <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl text-lg" style="background: color-mix(in srgb, var(--ui-primary) 16%, var(--ui-surface) 84%);">🐾</span>
                            <span class="shell-title text-base">{{ $appName }}</span>
                        </a>

                        <button type="button" class="icon-button" @click="closeMenus" aria-label="Close menu">
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M5 5l10 10M15 5L5 15" stroke-linecap="round" />
                            </svg>
                        </button>
                    </div>

                    <x-search-form :action="$searchTarget" class="w-full" placeholder="Search on LaraPets" />

                    <nav class="space-y-1" aria-label="Mobile Navigation Drawer">
                        @foreach ($desktopNav as $item)
                            @php
                                $href = ($item['route'] && Route::has($item['route']))
                                    ? route($item['route'])
                                    : ($item['href'] ?? '#');
                                $isActive = $item['route'] && $currentRoute
                                    ? str_starts_with($currentRoute, $item['route'])
                                    : false;
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
                            $href = ($item['route'] && Route::has($item['route']))
                                ? route($item['route'])
                                : ($item['href'] ?? '#');
                            $isActive = $item['route'] && $currentRoute
                                ? str_starts_with($currentRoute, $item['route'])
                                : false;
                        @endphp

                        <a
                            href="{{ $href }}"
                            class="flex min-w-0 flex-1 flex-col items-center gap-0.5 rounded-xl px-1 py-1 text-[0.65rem] font-semibold"
                            style="color: {{ $isActive ? 'var(--ui-primary)' : 'var(--ui-text-muted)' }};"
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
