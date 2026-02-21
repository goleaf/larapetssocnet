@php
    $appName ??= config('app.name', 'LaraPets');
    $searchTarget ??= Route::has('search.index') ? route('search.index') : url('/');
    $desktopNav ??= [];
    $currentRoute ??= Route::currentRouteName();
    $routeIsActive ??= static fn (array $patterns): bool => false;
    $user ??= Auth::user();

    $hasNotificationsTable = $user !== null && \Illuminate\Support\Facades\Schema::hasTable('notifications');
    $unreadNotificationsCount = $hasNotificationsTable ? (int) $user->unreadNotifications()->count() : 0;
    $unreadMessagesCount = $user ? (int) $user->unreadThreadsCount() : 0;
@endphp

<nav class="sticky top-0 z-40 border-b surface-glass" style="border-color: var(--ui-border);">
    <div class="mx-auto flex h-16 w-full max-w-[1400px] items-center gap-2 px-4 sm:px-6 lg:px-8">
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

        <a href="{{ Auth::check() && Route::has('feed.index') ? route('feed.index') : (Route::has('explore.index') ? route('explore.index') : url('/')) }}" class="flex shrink-0 items-center gap-2">
            <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl text-lg" style="background: color-mix(in srgb, var(--ui-primary) 16%, var(--ui-surface) 84%);">🐾</span>
            <span class="shell-title text-base sm:text-lg">{{ $appName }}</span>
            <span class="hidden text-xs font-semibold sm:inline text-gradient-brand">Social for pet lovers</span>
        </a>

        <div class="hidden flex-1 px-3 md:block">
            <x-search-form :action="$searchTarget" class="w-full max-w-xl" placeholder="Search pets, posts, groups, events..." />
        </div>

        <div class="ms-auto flex items-center gap-2">
            @auth
                @if (Route::has('messages.index'))
                    <a
                        href="{{ route('messages.index') }}"
                        class="icon-button relative"
                        title="Messages"
                        aria-label="Messages"
                    >
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7">
                            <path d="M3.3 5.2A2.2 2.2 0 0 1 5.5 3h9a2.2 2.2 0 0 1 2.2 2.2v5.4a2.2 2.2 0 0 1-2.2 2.2H9.8l-2.8 2.2v-2.2H5.5a2.2 2.2 0 0 1-2.2-2.2Z" stroke-linejoin="round" />
                        </svg>

                        @if ($unreadMessagesCount > 0)
                            <span class="absolute -right-1 -top-1 inline-flex min-w-5 items-center justify-center rounded-full bg-emerald-500 px-1.5 text-[0.65rem] font-semibold leading-5 text-white">
                                {{ $unreadMessagesCount > 99 ? '99+' : $unreadMessagesCount }}
                            </span>
                        @endif
                    </a>
                @endif

                @if (Route::has('notifications.index'))
                    <a
                        href="{{ route('notifications.index') }}"
                        class="icon-button relative"
                        title="Notifications"
                        aria-label="Notifications"
                    >
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7">
                            <path d="M10 3.4a4.2 4.2 0 0 1 4.2 4.2v2.2c0 .9.3 1.7.9 2.4l.8.9H4.1l.8-.9c.6-.7.9-1.5.9-2.4V7.6A4.2 4.2 0 0 1 10 3.4Z" stroke-linejoin="round" />
                            <path d="M8.2 14.4a1.8 1.8 0 0 0 3.6 0" stroke-linecap="round" />
                        </svg>

                        @if ($unreadNotificationsCount > 0)
                            <span class="absolute -right-1 -top-1 inline-flex min-w-5 items-center justify-center rounded-full bg-rose-500 px-1.5 text-[0.65rem] font-semibold leading-5 text-white">
                                {{ $unreadNotificationsCount > 99 ? '99+' : $unreadNotificationsCount }}
                            </span>
                        @endif
                    </a>
                @endif

                @if ($user?->is_private && (int) ($user->follow_requests_count ?? 0) > 0 && Route::has('follow-requests.index'))
                    <a
                        href="{{ route('follow-requests.index') }}"
                        class="rounded-full border border-amber-300 bg-amber-50 px-3 py-1.5 text-xs font-medium text-amber-700 transition-colors hover:bg-amber-100 dark:border-amber-700 dark:bg-amber-900/30 dark:text-amber-300 dark:hover:bg-amber-900/40"
                        aria-label="Open follow requests"
                    >
                        👥 {{ (int) $user->follow_requests_count }} request(s)
                    </a>
                @endif
            @endauth

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
