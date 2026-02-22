<nav class="sticky top-0 z-40 bg-warm-white/95 backdrop-blur-sm border-b border-whisker/30 shadow-sm w-full"
    x-data="{ mobileMenuOpen: false }">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex items-center">
                <a href="/" class="flex items-center gap-2 text-xl font-bold font-display text-bark">
                    🐾 PetSocNet
                </a>

                <div class="hidden md:ml-10 md:flex md:space-x-8 h-full">
                    <a href="/feed"
                        class="inline-flex items-center px-1 pt-1 h-full text-sm font-medium transition-colors {{ request()->routeIs('feed.*') || request()->is('feed') ? 'text-paw font-semibold border-b-2 border-paw' : 'text-fur hover:text-bark border-b-2 border-transparent' }}">
                        Feed
                    </a>
                    <a href="/groups"
                        class="inline-flex items-center px-1 pt-1 h-full text-sm font-medium transition-colors {{ request()->routeIs('groups.*') || request()->is('groups*') ? 'text-paw font-semibold border-b-2 border-paw' : 'text-fur hover:text-bark border-b-2 border-transparent' }}">
                        Groups
                    </a>
                    <a href="/explore"
                        class="inline-flex items-center px-1 pt-1 h-full text-sm font-medium transition-colors {{ request()->routeIs('explore.*') || request()->is('explore') ? 'text-paw font-semibold border-b-2 border-paw' : 'text-fur hover:text-bark border-b-2 border-transparent' }}">
                        Explore
                    </a>
                    <a href="/mypets"
                        class="inline-flex items-center px-1 pt-1 h-full text-sm font-medium transition-colors {{ request()->routeIs('pets.*') || request()->is('mypets') ? 'text-paw font-semibold border-b-2 border-paw' : 'text-fur hover:text-bark border-b-2 border-transparent' }}">
                        My Pets
                    </a>
                </div>
            </div>

            <div class="hidden md:flex md:items-center md:space-x-6">
                <!-- Notifications -->
                <button type="button"
                    class="text-fur hover:text-bark transition-colors relative p-1 rounded-full focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
                    <span class="sr-only">View notifications</span>
                    <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                        stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                    </svg>
                </button>

                <!-- Profile dropdown -->
                @auth
                    <x-ui.dropdown align="right" width="48">
                        <x-slot name="trigger">
                            <button type="button"
                                class="flex text-sm rounded-full focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw items-center gap-2"
                                id="user-menu-button">
                                <span class="sr-only">Open user menu</span>
                                <x-ui.avatar size="sm" :name="auth()->user()->name ?? 'User'"
                                    :src="auth()->user()?->avatar_url" />
                                <span
                                    class="text-sm font-medium text-bark hidden lg:block">{{ auth()->user()->name ?? 'User' }}</span>
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"
                                    class="w-4 h-4 text-fur">
                                    <path fill-rule="evenodd"
                                        d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z"
                                        clip-rule="evenodd" />
                                </svg>
                            </button>
                        </x-slot>

                        <x-slot name="content">
                            <x-ui.dropdown-item href="/profile">My Profile</x-ui.dropdown-item>
                            <x-ui.dropdown-item href="/settings">Settings</x-ui.dropdown-item>
                            <div class="border-t border-whisker/30 my-1"></div>
                            <form method="POST" action="/logout">
                                @csrf
                                <x-ui.dropdown-item variant="danger"
                                    onclick="event.preventDefault(); this.closest('form').submit();">
                                    Log Out
                                </x-ui.dropdown-item>
                            </form>
                        </x-slot>
                    </x-ui.dropdown>
                @else
                    <div class="flex items-center gap-3">
                        <x-ui.button href="/login" variant="ghost" size="sm">Log In</x-ui.button>
                        <x-ui.button href="/register" variant="primary" size="sm">Sign Up</x-ui.button>
                    </div>
                @endauth
            </div>

            <div class="flex items-center md:hidden">
                <button type="button" @click="mobileMenuOpen = !mobileMenuOpen"
                    class="inline-flex items-center justify-center p-2 rounded-md text-fur hover:text-bark hover:bg-cream transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
                    <span class="sr-only">Open main menu</span>
                    <svg x-show="!mobileMenuOpen" class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none"
                        viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                    <svg x-show="mobileMenuOpen" style="display: none;" class="h-6 w-6"
                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Mobile menu -->
    <div x-show="mobileMenuOpen" style="display: none;" x-collapse
        class="md:hidden border-t border-whisker/30 bg-warm-white">
        <div class="space-y-1 pb-3 pt-2">
            <a href="/feed"
                class="block py-2 pl-3 pr-4 text-base font-medium {{ request()->routeIs('feed.*') || request()->is('feed') ? 'bg-paw-light border-l-4 border-paw text-paw-dark' : 'border-l-4 border-transparent text-fur hover:bg-cream hover:text-bark' }}">
                Feed
            </a>
            <a href="/groups"
                class="block py-2 pl-3 pr-4 text-base font-medium {{ request()->routeIs('groups.*') || request()->is('groups*') ? 'bg-paw-light border-l-4 border-paw text-paw-dark' : 'border-l-4 border-transparent text-fur hover:bg-cream hover:text-bark' }}">
                Groups
            </a>
            <a href="/explore"
                class="block py-2 pl-3 pr-4 text-base font-medium {{ request()->routeIs('explore.*') || request()->is('explore') ? 'bg-paw-light border-l-4 border-paw text-paw-dark' : 'border-l-4 border-transparent text-fur hover:bg-cream hover:text-bark' }}">
                Explore
            </a>
            <a href="/mypets"
                class="block py-2 pl-3 pr-4 text-base font-medium {{ request()->routeIs('pets.*') || request()->is('mypets') ? 'bg-paw-light border-l-4 border-paw text-paw-dark' : 'border-l-4 border-transparent text-fur hover:bg-cream hover:text-bark' }}">
                My Pets
            </a>
        </div>

        @auth
            <div class="border-t border-whisker/30 pb-3 pt-4">
                <div class="flex items-center px-4">
                    <div class="shrink-0">
                        <x-ui.avatar size="md" :name="auth()->user()->name ?? 'User'" :src="auth()->user()?->avatar_url" />
                    </div>
                    <div class="ml-3">
                        <div class="text-base font-medium text-bark">{{ auth()->user()->name ?? 'User' }}</div>
                        <div class="text-sm font-medium text-fur">{{ auth()->user()->email ?? '' }}</div>
                    </div>
                </div>
                <div class="mt-3 space-y-1">
                    <a href="/profile"
                        class="block px-4 py-2 text-base font-medium text-fur hover:bg-cream hover:text-bark">My Profile</a>
                    <a href="/settings"
                        class="block px-4 py-2 text-base font-medium text-fur hover:bg-cream hover:text-bark">Settings</a>
                    <form method="POST" action="/logout">
                        @csrf
                        <button type="submit"
                            class="block w-full text-left px-4 py-2 text-base font-medium text-rose hover:bg-rose-light">Log
                            Out</button>
                    </form>
                </div>
            </div>
        @else
            <div class="border-t border-whisker/30 p-4 space-y-3">
                <x-ui.button href="/login" variant="ghost" full>Log In</x-ui.button>
                <x-ui.button href="/register" variant="primary" full>Sign Up</x-ui.button>
            </div>
        @endauth
    </div>
</nav>