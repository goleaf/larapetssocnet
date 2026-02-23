@php
 $currentRoute = Route::currentRouteName();

 $links = [
 [
'label'=>'Feed',
'route'=> Route::has('feed.index') ?'feed.index': null,
'href'=>'/feed',
'patterns'=> ['feed.*','posts.*','saved.*'],
 ],
 [
'label'=>'Groups',
'route'=> Route::has('groups.index') ?'groups.index': null,
'href'=>'/groups',
'patterns'=> ['groups.*'],
 ],
 [
'label'=>'Explore',
'route'=> Route::has('explore.index') ?'explore.index': null,
'href'=>'/explore',
'patterns'=> ['explore.*','search.*','hashtags.*'],
 ],
 [
'label'=>'My Pets',
'route'=> Route::has('pets.explore') ?'pets.explore': null,
'href'=>'/mypets',
'patterns'=> ['pets.*'],
 ],
 ];

 $resolveHref = static function (array $item): string {
 if (!empty($item['route']) && Route::has($item['route'])) {
 return route($item['route']);
 }

 return $item['href'] ??'#';
 };

 $isActive = static function (array $patterns) use ($currentRoute): bool {
 if (!$currentRoute) {
 return false;
 }

 foreach ($patterns as $pattern) {
 if (\Illuminate\Support\Str::is((string) $pattern, $currentRoute)) {
 return true;
 }
 }

 return false;
 };
@endphp

<nav class="sticky top-0 z-40 w-full border-b border-whisker/30 bg-warm-white/95 shadow-sm backdrop-blur-sm"
 x-data="{ mobileMenuOpen: false }"@keydown.escape.window="mobileMenuOpen = false">
 <div class="mx-auto h-16 max-w-7xl px-4 sm:px-6 lg:px-8">
 <div class="flex h-full items-center justify-between">
 <div class="flex items-center">
 <a href="{{ url('/') }}"class="flex items-center gap-2 text-xl font-bold font-display text-bark">
 <span aria-hidden="true">🐾</span>
 <span>{{ config('app.name','PetSocNet') }}</span>
 </a>

 <div class="ml-10 hidden h-full md:flex md:space-x-8">
 @foreach($links as $link)
 @php
 $active = $isActive($link['patterns']);
 @endphp

 <a href="{{ $resolveHref($link) }}"
 class="inline-flex h-full items-center border-b-2 px-1 pt-1 text-sm font-medium transition-colors {{ $active ?'border-paw font-semibold text-paw':'border-transparent text-fur hover:text-bark'}}"
 @if($active) aria-current="page"@endif>
 {{ $link['label'] }}
 </a>
 @endforeach
 </div>
 </div>

 <div class="hidden items-center space-x-6 md:flex">
 <button type="button"
 class="relative rounded-full p-1 text-fur transition-colors hover:text-bark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
 <span class="sr-only">View notifications</span>
 <svg class="h-6 w-6"xmlns="http://www.w3.org/2000/svg"fill="none"viewBox="0 0 24 24"
 stroke-width="1.5"stroke="currentColor"aria-hidden="true">
 <path stroke-linecap="round"stroke-linejoin="round"
 d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/>
 </svg>
 </button>

 @auth
 <x-ui.dropdown align="right"width="48">
 <x-slot name="trigger">
 <button type="button"
 class="flex items-center gap-2 rounded-full text-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw"
 id="user-menu-button">
 <span class="sr-only">Open user menu</span>
 <x-ui.avatar size="sm":name="auth()->user()->name ??'User'"
 :src="auth()->user()?->avatar_url"/>
 <span
 class="hidden text-sm font-medium text-bark lg:block">{{ auth()->user()->name ??'User'}}</span>
 <svg xmlns="http://www.w3.org/2000/svg"viewBox="0 0 20 20"fill="currentColor"
 class="h-4 w-4 text-fur"aria-hidden="true">
 <path fill-rule="evenodd"
 d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z"
 clip-rule="evenodd"/>
 </svg>
 </button>
 </x-slot>

 <x-slot name="content">
 <x-ui.dropdown-item :href="Route::has('profile.show') ? route('profile.show', auth()->user()) :'/profile'">My Profile</x-ui.dropdown-item>
 <x-ui.dropdown-item :href="Route::has('settings.profile') ? route('settings.profile') :'/settings'">Settings</x-ui.dropdown-item>
 <div class="my-1 border-t border-whisker/30"></div>
 <form method="POST"action="{{ Route::has('logout') ? route('logout') :'/logout'}}">
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
 <x-ui.button :href="Route::has('login') ? route('login') :'/login'"variant="ghost"size="sm">Log
 In</x-ui.button>
 <x-ui.button :href="Route::has('register') ? route('register') :'/register'"variant="primary"
 size="sm">Sign Up</x-ui.button>
 </div>
 @endauth
 </div>

 <div class="flex items-center md:hidden">
 <button type="button"@click="mobileMenuOpen = !mobileMenuOpen"
 class="inline-flex items-center justify-center rounded-md p-2 text-fur transition-colors hover:bg-cream hover:text-bark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
 <span class="sr-only">Open main menu</span>
 <svg x-show="!mobileMenuOpen"class="h-6 w-6"xmlns="http://www.w3.org/2000/svg"fill="none"
 viewBox="0 0 24 24"stroke-width="1.5"stroke="currentColor"aria-hidden="true">
 <path stroke-linecap="round"stroke-linejoin="round"
 d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/>
 </svg>
 <svg x-show="mobileMenuOpen"x-cloak class="h-6 w-6"xmlns="http://www.w3.org/2000/svg"fill="none"
 viewBox="0 0 24 24"stroke-width="1.5"stroke="currentColor"aria-hidden="true">
 <path stroke-linecap="round"stroke-linejoin="round"d="M6 18L18 6M6 6l12 12"/>
 </svg>
 </button>
 </div>
 </div>
 </div>

 <div x-show="mobileMenuOpen"x-cloak style="display: none;"x-collapse
 class="border-t border-whisker/30 bg-warm-white md:hidden">
 <div class="space-y-1 pb-3 pt-2">
 @foreach($links as $link)
 @php
 $active = $isActive($link['patterns']);
 @endphp

 <a href="{{ $resolveHref($link) }}"
 class="block border-l-4 py-2 pl-3 pr-4 text-base font-medium {{ $active ?'border-paw bg-paw-light text-paw-dark':'border-transparent text-fur hover:bg-cream hover:text-bark'}}"
 @if($active) aria-current="page"@endif>
 {{ $link['label'] }}
 </a>
 @endforeach
 </div>

 @auth
 <div class="border-t border-whisker/30 pb-3 pt-4">
 <div class="flex items-center px-4">
 <x-ui.avatar size="md":name="auth()->user()->name ??'User'":src="auth()->user()?->avatar_url"/>

 <div class="ml-3 min-w-0">
 <div class="truncate text-base font-medium text-bark">{{ auth()->user()->name ??'User'}}</div>
 <div class="truncate text-sm font-medium text-fur">{{ auth()->user()->email ??''}}</div>
 </div>
 </div>

 <div class="mt-3 space-y-1">
 <a href="{{ Route::has('profile.show') ? route('profile.show', auth()->user()) :'/profile'}}"
 class="block px-4 py-2 text-base font-medium text-fur hover:bg-cream hover:text-bark">My Profile</a>
 <a href="{{ Route::has('settings.profile') ? route('settings.profile') :'/settings'}}"
 class="block px-4 py-2 text-base font-medium text-fur hover:bg-cream hover:text-bark">Settings</a>

 <form method="POST"action="{{ Route::has('logout') ? route('logout') :'/logout'}}">
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
 <x-ui.button :href="Route::has('login') ? route('login') :'/login'"variant="ghost"full>Log
 In</x-ui.button>
 <x-ui.button :href="Route::has('register') ? route('register') :'/register'"variant="primary"full>Sign
 Up</x-ui.button>
 </div>
 @endauth
 </div>
</nav>