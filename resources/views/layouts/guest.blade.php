<!DOCTYPE html>
<html lang="{{ str_replace('_','-', app()->getLocale()) }}">
 <head>
 <meta charset="utf-8">
 <meta name="viewport" content=" width=device-width, initial-scale=1">
 <meta name="csrf-token" content="{{ csrf_token() }}">

 <title>{{ config('app.name','LaraPets') }}</title>

 <link rel="preconnect" href="https://fonts.bunny.net">
 <link href="https://fonts.bunny.net/css?family=outfit:500,600,700,800|nunito-sans:400,500,600,700&display=swap" rel="stylesheet" />

 <script>
 (() => {
 const key ='larapets-theme';
 const storedTheme = localStorage.getItem(key);
 const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
 const theme = storedTheme ==='light'|| storedTheme ==='dark'
 ? storedTheme
 : (prefersDark ?'dark':'light');

 document.documentElement.setAttribute('data-theme', theme);
 document.documentElement.classList.toggle('dark', theme ==='dark');
 })();
 </script>

 @vite(['resources/css/app.css','resources/js/app.js'])
 </head>
 <body class="antialiased" x-data="themeController()">
 <div class="relative flex min-h-screen">
 <section
 class="relative hidden w-1/2 overflow-hidden border-r px-10 py-12 lg:flex lg:flex-col lg:justify-between"
 style="border-color: var(--ui-border); background: linear-gradient(145deg, color-mix(in srgb, var(--ui-primary) 14%, var(--ui-bg) 86%), color-mix(in srgb, var(--ui-accent) 11%, var(--ui-bg) 89%));"
 >
 <div class="relative z-10">
 <a href="/" class="inline-flex items-center gap-3">
 <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl text-xl" style="background: color-mix(in srgb, var(--ui-primary) 20%, var(--ui-surface) 80%);">🐾</span>
 <span class="shell-title text-xl">{{ config('app.name','LaraPets') }}</span>
 </a>
 </div>

 <div class="relative z-10 max-w-lg space-y-5">
 <p class="chip">Pet-first social network</p>
 <h1 class="shell-title text-4xl text-balance">Find your next pet friend, foster home, or community walk.</h1>
 <p class="max-w-md text-base shell-text-muted">
 Keep things simple: log in, share updates, and connect with people who care about pets just as much as you do.
 </p>
 </div>

 <div class="relative z-10 grid grid-cols-3 gap-3 text-sm">
 <div class="shell-card p-3">
 <p class="text-xs uppercase tracking-[0.08em] shell-text-muted">Nearby pets</p>
 <p class="mt-1 shell-title text-lg" style="color: var(--ui-primary)">54</p>
 </div>
 <div class="shell-card p-3">
 <p class="text-xs uppercase tracking-[0.08em] shell-text-muted">Groups</p>
 <p class="mt-1 shell-title text-lg" style="color: var(--ui-accent)">18</p>
 </div>
 <div class="shell-card p-3">
 <p class="text-xs uppercase tracking-[0.08em] shell-text-muted">Events</p>
 <p class="mt-1 shell-title text-lg" style="color: var(--ui-secondary)">12</p>
 </div>
 </div>

 <div class="pointer-events-none absolute -bottom-20 -right-12 h-72 w-72 rounded-full" style="background: radial-gradient(circle, color-mix(in srgb, var(--ui-secondary) 20%, transparent), transparent 65%);"></div>
 <div class="pointer-events-none absolute -left-10 top-10 h-48 w-48 rounded-full" style="background: radial-gradient(circle, color-mix(in srgb, var(--ui-accent) 18%, transparent), transparent 70%);"></div>
 </section>

 <section class="relative flex flex-1 items-center justify-center px-4 py-8 sm:px-8 lg:px-10">
 <button
 type="button"
 class="icon-button absolute right-4 top-4 sm:right-6 sm:top-6"
 @click="toggleTheme"
 :aria-label="isDark ?'Switch to light mode':'Switch to dark mode'"
 >
 <svg x-show="!isDark"x-cloak class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6">
 <circle cx="10" cy="10" r="3.3" />
 <path d="M10 1.7v2.2M10 16.1v2.2M3.9 3.9l1.6 1.6M14.5 14.5l1.6 1.6M1.7 10h2.2M16.1 10h2.2M3.9 16.1l1.6-1.6M14.5 5.5l1.6-1.6" stroke-linecap="round" />
 </svg>
 <svg x-show="isDark"x-cloak class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6">
 <path d="M13.9 2.3a7.3 7.3 0 1 0 3.8 13.6 7.5 7.5 0 0 1-3.8-13.6Z" stroke-linejoin="round" />
 </svg>
 </button>

 <div class="w-full max-w-md space-y-4">
 <a href="/" class="inline-flex items-center gap-2 lg:hidden">
 <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl text-lg" style="background: color-mix(in srgb, var(--ui-primary) 16%, var(--ui-surface) 84%);">🐾</span>
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
