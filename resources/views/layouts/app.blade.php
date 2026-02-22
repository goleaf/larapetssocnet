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
    <body class="antialiased font-body bg-cream text-bark" x-data="appShell()">
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

            <div @class([
                'relative z-10 mx-auto grid w-full max-w-7xl grid-cols-1 gap-5 px-4 pb-24 pt-2 sm:px-6 lg:gap-6 lg:px-8 lg:pb-8',
                'lg:grid-cols-[16.5rem_minmax(0,1fr)_20rem]' => ! $hideLeftRail,
                'lg:grid-cols-[minmax(0,1fr)_20rem]' => $hideLeftRail,
            ])>
                @unless ($hideLeftRail)
                <aside class="hidden lg:block">
                    <div class="sticky top-24 space-y-4">
                        <x-ui.card>
                            <div class="flex items-center gap-3">
                                <x-ui.avatar :name="$user?->name ?? 'Guest User'" :src="$user?->avatar_url" size="lg" />
                                <div class="min-w-0">
                                    <p class="truncate text-base font-semibold text-bark">{{ $user?->name ?? 'Guest User' }}</p>
                                    <p class="truncate text-xs text-fur">{{ $user?->username ? '@'.$user->username : ($user?->email ?? 'community@larapets.test') }}</p>
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
                            @php
                                $mappedNav = collect($desktopNav)->map(function($item) {
                                    return [
                                        'label' => $item['label'],
                                        'href' => isset($item['route']) && Route::has($item['route']) ? route($item['route']) : '#',
                                        'icon' => '<span class="text-lg leading-none">' . $item['icon'] . '</span>',
                                        'pattern' => $item['patterns'] ?? [],
                                    ];
                                })->toArray();
                            @endphp
                            <x-ui.sidebar-nav :items="$mappedNav" class="!mb-0" />
                        </x-ui.card>

                        <x-ui.card>
                            <div class="mb-2 flex items-center justify-between">
                                <h4 class="text-xs font-bold font-display uppercase tracking-wider text-fur">Trending Tags</h4>
                                <x-ui.badge variant="success" size="sm" pill>Live</x-ui.badge>
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
                                <x-ui.badge variant="primary" size="sm" pill>PetSocial</x-ui.badge>
                            </div>
                        </x-ui.card>
                    @endisset

                    <section class="space-y-5 animate-fade-up">
                        {{ $slot }}
                    </section>
                </main>

                <aside class="hidden lg:block">
                    <div class="sticky top-24 space-y-4">
                        <x-ui.card>
                            <div class="mb-3 flex items-center justify-between">
                                <h4 class="text-xs font-bold font-display uppercase tracking-wider text-fur">Who To Follow</h4>
                                <a href="{{ Route::has('search.index') ? route('search.index', ['type' => 'users']) : '#' }}" class="text-xs font-semibold hover:underline text-paw">See all</a>
                            </div>

                            <div class="space-y-1 -mx-2">
                                @forelse ($suggestedUsers as $suggested)
                                    <x-ui.user-row 
                                        :name="$suggested->name" 
                                        :subtitle="$suggested->username ? '@'.$suggested->username : 'Pet lover'" 
                                        :avatar="$suggested->avatar_url" 
                                        :href="route('profile.show', ['user' => $suggested])"
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
                                <a href="{{ Route::has('events.index') ? route('events.index') : '#' }}" class="text-xs font-semibold hover:underline text-paw">Browse</a>
                            </div>

                            <div class="space-y-2 mt-3">
                                @forelse ($upcomingEvents as $event)
                                    <a href="{{ route('events.show', $event) }}" class="block rounded-xl border border-whisker/30 bg-warm-white px-3 py-2 hover:bg-cream transition-colors shadow-sm">
                                        <p class="line-clamp-1 text-sm font-semibold text-bark">{{ $event->title }}</p>
                                        <p class="mt-0.5 text-xs text-fur">
                                            {{ optional($event->start_at)->format('M j, g:i A') ?? 'Date TBD' }}
                                            <span class="mx-1">•</span>
                                            {{ $event->location_text ?: 'Online / TBD' }}
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
                                <x-ui.badge variant="success" size="sm" pill>{{ $activeContests->count() }}</x-ui.badge>
                            </div>

                            <div class="space-y-2 mt-3">
                                @forelse ($activeContests as $contest)
                                    <a href="{{ Route::has('contests.index') ? route('contests.index') : '#' }}" class="block rounded-xl border border-whisker/30 bg-warm-white px-3 py-2 hover:bg-cream transition-colors shadow-sm">
                                        <p class="line-clamp-1 text-sm font-semibold text-bark">{{ $contest->title }}</p>
                                        <p class="mt-0.5 text-xs text-fur">{{ ucfirst((string) $contest->status) }} · {{ number_format((int) $contest->entries_count) }} entries</p>
                                    </a>
                                @empty
                                    <p class="text-sm text-fur">No active contests right now.</p>
                                @endforelse
                            </div>
                        </x-ui.card>
                    </div>
                </aside>
            </div>
            
            <!-- Mobile Bottom Nav -->
            <nav class="fixed inset-x-3 bottom-3 z-40 lg:hidden">
                <div class="bg-warm-white rounded-2xl shadow-card-hover border border-whisker/30 flex items-center justify-between px-2 py-1.5">
                    @foreach ($mobileNav as $item)
                        @php
                            $href = ($item['route'] && Route::has($item['route'])) ? route($item['route']) : '#';
                            $isActive = $routeIsActive($item['patterns'] ?? []);
                            $isPrimaryAction = ($item['route'] ?? null) === 'posts.create';
                        @endphp

                        <a
                            href="{{ $href }}"
                            class="flex min-w-0 flex-1 flex-col items-center gap-0.5 rounded-xl px-1 py-1 text-[0.65rem] font-semibold transition-colors {{ $isPrimaryAction ? 'bg-paw text-white shadow-button hover:bg-paw-dark' : ($isActive ? 'text-paw' : 'text-fur hover:bg-cream hover:text-bark') }}"
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
