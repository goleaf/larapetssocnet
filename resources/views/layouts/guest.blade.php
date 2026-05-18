<!DOCTYPE html>
<html lang="{{ str_replace('_','-', app()->getLocale()) }}" data-theme="petssocnet">
 <head>
 <meta charset="utf-8">
 <meta name="viewport" content="width=device-width, initial-scale=1">
 <meta name="csrf-token" content="{{ csrf_token() }}">

 <title>{{ config('app.name','LaraPets') }}</title>

 @vite(['resources/scss/app.scss', 'resources/js/app.js'])
 </head>
 <body class="antialiased">
 <div class="relative flex min-h-screen" data-ui="guest-shell">
 <section
 class="relative hidden w-1/2 overflow-hidden border-r border-[var(--border-soft)] bg-[color:var(--surface-page)] px-10 py-12 lg:flex lg:flex-col lg:justify-between"
 >
 <div class="relative z-10">
 <a href="/" class="inline-flex min-h-11 items-center gap-3 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
 <span class="inline-flex h-11 w-11 items-center justify-center rounded-[var(--radius-soft)] bg-paw-light text-xl text-paw-dark">🐾</span>
 <span class="shell-title text-xl">{{ config('app.name','LaraPets') }}</span>
 </a>
 </div>

 <div class="relative z-10 max-w-lg space-y-6">
 <p class="chip min-h-8">Pet-first social network</p>
 <h1 class="shell-title text-4xl text-balance">Find your next pet friend, foster home, or community walk.</h1>
 <p class="max-w-md text-base leading-7 shell-text-muted">
 Keep things simple: log in, share updates, and connect with people who care about pets just as much as you do.
 </p>

 <div class="grid gap-3">
 <a href="{{ route('explore.index') }}" class="shell-card ui-card-interactive group flex min-h-16 items-center justify-between gap-4 px-4 py-3 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
 <span>
 <span class="block text-sm font-semibold text-bark group-hover:text-paw">Explore posts</span>
 <span class="text-xs shell-text-muted">Public photos, stories, and updates</span>
 </span>
 <span class="text-lg" aria-hidden="true">→</span>
 </a>

 <a href="{{ route('pets.adopt') }}" class="shell-card ui-card-interactive group flex min-h-16 items-center justify-between gap-4 px-4 py-3 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
 <span>
 <span class="block text-sm font-semibold text-bark group-hover:text-paw">Browse adoption</span>
 <span class="text-xs shell-text-muted">Pets currently looking for homes</span>
 </span>
 <span class="text-lg" aria-hidden="true">→</span>
 </a>

 <a href="{{ route('events.index') }}" class="shell-card ui-card-interactive group flex min-h-16 items-center justify-between gap-4 px-4 py-3 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
 <span>
 <span class="block text-sm font-semibold text-bark group-hover:text-paw">Find events</span>
 <span class="text-xs shell-text-muted">Walks, meetups, and group activities</span>
 </span>
 <span class="text-lg" aria-hidden="true">→</span>
 </a>
 </div>
 </div>

 <div class="relative z-10 grid grid-cols-3 gap-3 text-sm">
 <div class="shell-card p-3">
 <p class="text-xs uppercase tracking-[0.08em] shell-text-muted">Nearby pets</p>
 <p class="mt-1 shell-title text-lg text-paw">54</p>
 </div>
 <div class="shell-card p-3">
 <p class="text-xs uppercase tracking-[0.08em] shell-text-muted">Groups</p>
 <p class="mt-1 shell-title text-lg text-sky">18</p>
 </div>
 <div class="shell-card p-3">
 <p class="text-xs uppercase tracking-[0.08em] shell-text-muted">Events</p>
 <p class="mt-1 shell-title text-lg text-amber">12</p>
 </div>
 </div>

 </section>

 <section class="relative flex flex-1 items-center justify-center px-4 py-8 sm:px-8 lg:px-10">
 <div class="w-full max-w-md space-y-4" data-ui="guest-auth-panel">
 <a href="/" class="inline-flex min-h-11 items-center gap-2 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw lg:hidden">
 <span class="inline-flex h-10 w-10 items-center justify-center rounded-[var(--radius-soft)] bg-paw-light text-lg text-paw-dark">🐾</span>
 <span class="shell-title text-lg">{{ config('app.name','LaraPets') }}</span>
 </a>

 <div class="shell-card px-5 py-6 sm:px-6 sm:py-7">
 {{ $slot }}
 </div>
 </div>
 </section>
 </div>
 </body>
</html>
