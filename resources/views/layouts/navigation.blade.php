@php
    $appName ??= config('app.name', 'LaraPets');
    $searchTarget ??= Route::has('dashboard') ? route('dashboard') : url('/');
    $desktopNav ??= [];
    $currentRoute ??= Route::currentRouteName();
    $user ??= Auth::user();
@endphp

<nav class="sticky top-0 z-40 border-b surface-glass" style="border-color: var(--ui-border);">
    <div class="mx-auto flex h-16 w-full max-w-7xl items-center gap-2 px-4 sm:px-6 lg:px-8">
        <button
            type="button"
            class="icon-button lg:hidden"
            @click="toggleMobileMenu"
            aria-label="Toggle mobile menu"
            :aria-expanded="mobileMenuOpen.toString()"
        >
            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8">
                <path d="M3.5 5.5h13M3.5 10h13M3.5 14.5h13" stroke-linecap="round" />
            </svg>
        </button>

        <a href="{{ Route::has('dashboard') ? route('dashboard') : url('/') }}" class="flex shrink-0 items-center gap-2">
            <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl text-lg" style="background: color-mix(in srgb, var(--ui-primary) 16%, var(--ui-surface) 84%);">🐾</span>
            <span class="shell-title text-base sm:text-lg">{{ $appName }}</span>
        </a>

        <div class="hidden flex-1 px-3 md:block">
            <x-search-form :action="$searchTarget" class="w-full max-w-xl" placeholder="Search pets, posts, groups, events..." />
        </div>

        <div class="hidden items-center gap-1 xl:flex">
            @foreach (array_slice($desktopNav, 0, 3) as $item)
                @php
                    $href = ($item['route'] && Route::has($item['route']))
                        ? route($item['route'])
                        : ($item['href'] ?? '#');
                    $isActive = $item['route'] && $currentRoute
                        ? str_starts_with($currentRoute, $item['route'])
                        : false;
                @endphp

                <a href="{{ $href }}" class="shell-nav-link {{ $isActive ? 'active' : '' }} px-3 py-2 text-sm">
                    <span aria-hidden="true">{{ $item['icon'] }}</span>
                    <span>{{ $item['label'] }}</span>
                </a>
            @endforeach
        </div>

        <div class="ms-auto flex items-center gap-2">
            <button
                type="button"
                class="icon-button"
                @click="toggleTheme"
                :aria-label="isDark ? 'Switch to light mode' : 'Switch to dark mode'"
                :title="isDark ? 'Switch to light mode' : 'Switch to dark mode'"
            >
                <svg x-show="!isDark" x-cloak class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6">
                    <circle cx="10" cy="10" r="3.3" />
                    <path d="M10 1.7v2.2M10 16.1v2.2M3.9 3.9l1.6 1.6M14.5 14.5l1.6 1.6M1.7 10h2.2M16.1 10h2.2M3.9 16.1l1.6-1.6M14.5 5.5l1.6-1.6" stroke-linecap="round" />
                </svg>
                <svg x-show="isDark" x-cloak class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6">
                    <path d="M13.9 2.3a7.3 7.3 0 1 0 3.8 13.6 7.5 7.5 0 0 1-3.8-13.6Z" stroke-linejoin="round" />
                </svg>
            </button>

            <x-dropdown align="right" width="64">
                <x-slot name="trigger">
                    <button type="button" class="btn-base btn-ghost gap-2 px-2.5 py-2 text-sm">
                        <x-avatar :src="$user?->avatar_url" :name="$user?->name ?? 'Guest User'" size="sm" />
                        <span class="hidden max-w-[8rem] truncate sm:inline">{{ $user?->name ?? 'Guest' }}</span>
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="m6.5 8 3.5 4 3.5-4" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </button>
                </x-slot>

                <x-slot name="content">
                    @auth
                        @if ($user?->username && Route::has('profile.show'))
                            <x-dropdown-link :href="route('profile.show', ['user' => $user])">View Profile</x-dropdown-link>
                        @endif

                        @if (Route::has('settings.profile.edit'))
                            <x-dropdown-link :href="route('settings.profile.edit')">Settings: Profile</x-dropdown-link>
                        @endif

                        @if (Route::has('settings.account.edit'))
                            <x-dropdown-link :href="route('settings.account.edit')">Settings: Account</x-dropdown-link>
                        @endif
                    @endauth

                    @auth
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf

                            <x-dropdown-link
                                :href="route('logout')"
                                onclick="event.preventDefault(); this.closest('form').submit();"
                            >
                                Log Out
                            </x-dropdown-link>
                        </form>
                    @else
                        @if (Route::has('login'))
                            <x-dropdown-link :href="route('login')">Log In</x-dropdown-link>
                        @endif
                    @endauth
                </x-slot>
            </x-dropdown>
        </div>
    </div>
</nav>
