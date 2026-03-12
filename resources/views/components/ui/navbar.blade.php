<nav class="sticky top-0 z-40 w-full border-b border-whisker/30 bg-warm-white/95 shadow-sm backdrop-blur-sm"
 x-data="{ mobileMenuOpen: false }" @keydown.escape.window="mobileMenuOpen = false">
 <div class="mx-auto h-16 max-w-7xl px-4 sm:px-6 lg:px-8">
 <div class="flex h-full items-center justify-between">
 <div class="flex items-center">
 <a href="{{ $homeHref }}" class="flex items-center gap-2 text-xl font-bold font-display text-bark">
 <span aria-hidden="true">🐾</span>
 <span>{{ config('app.name', 'PetSocNet') }}</span>
 </a>

 <div class="ml-10 hidden h-full md:flex md:space-x-8">
 @foreach ($links as $link)
 <a href="{{ $link['href'] }}"
 class="inline-flex h-full items-center border-b-2 px-1 pt-1 text-sm font-medium transition-colors {{ $link['active'] ? 'border-paw font-semibold text-paw' : 'border-transparent text-fur hover:text-bark' }}"
 @if($link['active']) aria-current="page" @endif>
 {{ $link['label'] }}
 </a>
 @endforeach
 </div>
 </div>

 <div class="hidden items-center space-x-6 md:flex">
 @auth
 @if (Route::has('messages.index'))
 <a href="{{ route('messages.index') }}" class="relative p-1 text-fur transition-colors hover:text-bark"
 aria-label="Messages">
 <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
 stroke="currentColor" aria-hidden="true">
 <path stroke-linecap="round" stroke-linejoin="round"
 d="M2.25 12.76c0 1.6.84 3.08 2.2 3.93v3.07a.75.75 0 0 0 1.22.58l3.47-2.85a.75.75 0 0 1 .47-.17h1.6c4.4 0 7.98-3.1 7.98-6.92 0-3.83-3.57-6.93-7.98-6.93-4.4 0-7.98 3.1-7.98 6.92Z"/>
 </svg>
 @if ($unreadMessageCount > 0)
 <span
 class="absolute -right-1 -top-1 inline-flex min-w-5 items-center justify-center rounded-full bg-emerald-500 px-1.5 text-[0.65rem] font-semibold leading-5 text-white">
 {{ $unreadMessageCount > 99 ? '99+' : $unreadMessageCount }}
 </span>
 @endif
 </a>
 @endif

 @if (Route::has('notifications.index'))
 <a href="{{ route('notifications.index') }}" class="relative p-1 text-fur transition-colors hover:text-bark"
 aria-label="Notifications">
 <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
 stroke="currentColor" aria-hidden="true">
 <path stroke-linecap="round" stroke-linejoin="round"
 d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75v-.7V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"/>
 </svg>
 @if ($unreadNotificationsCount > 0)
 <span
 class="absolute -right-1 -top-1 inline-flex min-w-5 items-center justify-center rounded-full bg-rose-500 px-1.5 text-[0.65rem] font-semibold leading-5 text-white">
 {{ $unreadNotificationsCount > 99 ? '99+' : $unreadNotificationsCount }}
 </span>
 @endif
 </a>
 @endif
 @endauth

 <button type="button" class="icon-button" @click="toggleTheme"
 :aria-label="isDark ? 'Switch to light mode' : 'Switch to dark mode'"
 :title="isDark ? 'Switch to light mode' : 'Switch to dark mode'">
 <svg x-show="!isDark" x-cloak class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor"
 stroke-width="1.6">
 <circle cx="10" cy="10" r="3.3"/>
 <path
 d="M10 1.7v2.2M10 16.1v2.2M3.9 3.9l1.6 1.6M14.5 14.5l1.6 1.6M1.7 10h2.2M16.1 10h2.2M3.9 16.1l1.6-1.6M14.5 5.5l1.6-1.6"
 stroke-linecap="round"/>
 </svg>
 <svg x-show="isDark" x-cloak class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor"
 stroke-width="1.6">
 <path d="M13.9 2.3a7.3 7.3 0 1 0 3.8 13.6 7.5 7.5 0 0 1-3.8-13.6Z" stroke-linejoin="round"/>
 </svg>
 </button>

 <x-ui.dropdown align="right" width="48">
 <x-slot name="trigger">
 <button type="button"
 class="flex items-center gap-2 text-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw"
 id="user-menu-button">
 <span class="sr-only">Open user menu</span>
 <x-ui.avatar size="sm" :name="$user?->name ?? 'User'" :src="$user?->avatar_url"/>
 <span class="hidden max-w-[8rem] truncate text-sm font-medium text-bark lg:block">{{ $user?->name ?? 'User'}}</span>
 <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"
 class="h-4 w-4 text-fur" aria-hidden="true">
 <path fill-rule="evenodd"
 d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z"
 clip-rule="evenodd"/>
 </svg>
 </button>
 </x-slot>

 <x-slot name="content">
 @auth
 @if ($user && Route::has('profile.show'))
 <x-ui.dropdown-item :href="route('profile.show', $user)">My Profile</x-ui.dropdown-item>
 @endif
 @if (Route::has('settings.profile'))
 <x-ui.dropdown-item :href="route('settings.profile')">Settings</x-ui.dropdown-item>
 @endif
 <div class="my-1 border-t border-whisker/30"></div>
 <form method="POST" action="{{ route('logout') }}">
 @csrf
 <x-ui.dropdown-item variant="danger"
 onclick="event.preventDefault(); this.closest('form').submit();">
 Log Out
 </x-ui.dropdown-item>
 </form>
 @else
 @if (Route::has('login'))
 <x-ui.dropdown-item :href="route('login')">Log In</x-ui.dropdown-item>
 @endif
 @if (Route::has('register'))
 <x-ui.dropdown-item :href="route('register')">Sign Up</x-ui.dropdown-item>
 @endif
 @endauth
 </x-slot>
 </x-ui.dropdown>
 </div>

 <div class="flex items-center md:hidden">
 <button type="button" @click="mobileMenuOpen = !mobileMenuOpen"
 class="inline-flex items-center justify-center p-2 text-fur transition-colors hover:bg-cream hover:text-bark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
 <span class="sr-only">Open main menu</span>
 <svg x-show="!mobileMenuOpen" class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none"
 viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
 <path stroke-linecap="round" stroke-linejoin="round"
 d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/>
 </svg>
 <svg x-show="mobileMenuOpen" x-cloak class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none"
 viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
 <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
 </svg>
 </button>
 </div>
 </div>
 </div>

 <div x-show="mobileMenuOpen" x-cloak style="display: none;" x-collapse
 class="border-t border-whisker/30 bg-warm-white md:hidden">
 <div class="space-y-1 pb-3 pt-2">
 @foreach($links as $link)
 <a href="{{ $link['href'] }}"
 class="block border-l-4 py-2 pl-3 pr-4 text-base font-medium {{ $link['active'] ? 'border-paw bg-paw-light text-paw-dark' : 'border-transparent text-fur hover:bg-cream hover:text-bark' }}"
 @if($link['active']) aria-current="page" @endif>
 {{ $link['label'] }}
 </a>
 @endforeach
 </div>

 @auth
 <div class="border-t border-whisker/30 pb-3 pt-4">
 <div class="flex items-center px-4">
 <x-ui.avatar size="md" :name="$user?->name ?? 'User'" :src="$user?->avatar_url"/>

 <div class="ml-3 min-w-0">
 <div class="truncate text-base font-medium text-bark">{{ $user?->name ?? 'User' }}</div>
 <div class="truncate text-sm font-medium text-fur">{{ $user?->email ?? '' }}</div>
 </div>
 </div>

 <div class="mt-3 space-y-1">
 @if ($user && Route::has('profile.show'))
 <a href="{{ route('profile.show', $user) }}"
 class="block px-4 py-2 text-base font-medium text-fur hover:bg-cream hover:text-bark">My Profile</a>
 @endif
 @if (Route::has('settings.profile'))
 <a href="{{ route('settings.profile') }}"
 class="block px-4 py-2 text-base font-medium text-fur hover:bg-cream hover:text-bark">Settings</a>
 @endif
 <form method="POST" action="{{ route('logout') }}">
 @csrf
 <button type="submit"
 class="block w-full px-4 py-2 text-left text-base font-medium text-rose hover:bg-rose-light">
 Log Out
 </button>
 </form>
 </div>
 </div>
 @else
 <div class="space-y-3 border-t border-whisker/30 p-4">
 @if (Route::has('login'))
 <x-ui.button :href="route('login')" variant="ghost" full>Log In</x-ui.button>
 @endif
 @if (Route::has('register'))
 <x-ui.button :href="route('register')" variant="primary" full>Sign Up</x-ui.button>
 @endif
 </div>
 @endauth
 </div>
</nav>
