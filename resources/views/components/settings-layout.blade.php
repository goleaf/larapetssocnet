<x-app-layout>
 <x-slot name="title">Settings</x-slot>

 @php
 $settingsIcons = [
 'profile' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" /></svg>',
 'password' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z" /></svg>',
 'privacy' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" /></svg>',
 'notifications' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" /></svg>',
 'blocked' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" /></svg>',
 'data' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m6.75 12l-3-3m0 0l-3 3m3-3v6m-1.5-15H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>',
 ];

 $settingsNavItems = [
 [
 'label' => 'Profile',
 'route' => 'settings.profile',
 'routePattern' => 'settings.profile*',
 'icon' => $settingsIcons['profile'],
 ],
 [
 'label' => 'Password & Security',
 'route' => 'settings.password',
 'routePattern' => 'settings.password*',
 'icon' => $settingsIcons['password'],
 ],
 [
 'label' => 'Privacy',
 'route' => 'settings.privacy',
 'routePattern' => 'settings.privacy*',
 'icon' => $settingsIcons['privacy'],
 ],
 [
 'label' => 'Notifications',
 'route' => 'settings.notifications',
 'routePattern' => 'settings.notifications*',
 'icon' => $settingsIcons['notifications'],
 ],
 [
 'label' => 'Blocked Users',
 'route' => 'settings.blocked',
 'routePattern' => 'settings.blocked*',
 'icon' => $settingsIcons['blocked'],
 ],
 [
 'label' => 'Data & Account',
 'route' => 'settings.data',
 'routePattern' => 'settings.data*',
 'icon' => $settingsIcons['data'],
 ],
 ];
 @endphp

 <div class="py-6" data-ui="settings-shell">
 <div class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
 <section class="mb-5 overflow-hidden rounded-[var(--radius-card)] border border-whisker/40 bg-warm-white shadow-card" data-ui="settings-header">
 <div class="grid gap-4 px-5 py-5 sm:px-6 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center">
 <div class="space-y-2">
 <p class="chip min-h-8">Account controls</p>
 <h1 class="shell-title text-2xl">Settings</h1>
 <p class="max-w-2xl text-sm leading-6 shell-text-muted">
 Keep your profile, privacy, notifications, and account data in one predictable workspace.
 </p>
 </div>

 <div class="grid grid-cols-3 gap-2 text-center text-xs">
 <div class="rounded-[var(--radius-soft)] border border-whisker/30 bg-cream/60 px-3 py-2">
 <p class="font-semibold text-bark">Profile</p>
 <p class="text-fur">Identity</p>
 </div>
 <div class="rounded-[var(--radius-soft)] border border-whisker/30 bg-cream/60 px-3 py-2">
 <p class="font-semibold text-bark">Privacy</p>
 <p class="text-fur">Visibility</p>
 </div>
 <div class="rounded-[var(--radius-soft)] border border-whisker/30 bg-cream/60 px-3 py-2">
 <p class="font-semibold text-bark">Data</p>
 <p class="text-fur">Export</p>
 </div>
 </div>
 </div>
 </section>

 <div class="grid gap-5 lg:grid-cols-[16rem_minmax(0,1fr)]">
 <aside data-ui="settings-sidebar">
 <div class="sticky top-24 flex flex-col gap-4">
 <x-ui.card class="shadow-card">
 <x-ui.card-header title="Settings" subtitle="Account, privacy, and security"/>

 <x-ui.sidebar-nav :items="$settingsNavItems"/>
 </x-ui.card>
 </div>
 </aside>

 <main data-ui="settings-main">
 <x-ui.panel padding="lg" data-ui="settings-panel">
 {{ $slot }}
 </x-ui.panel>
 </main>
 </div>
 </div>
 </div>
</x-app-layout>
