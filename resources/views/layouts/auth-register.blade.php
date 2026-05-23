<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
 <head>
 <meta charset="utf-8">
 <meta name="viewport" content="width=device-width, initial-scale=1">
 <meta name="csrf-token" content="{{ csrf_token() }}">

 <title>{{ $title ?? config('app.name', 'PetSocial') }}</title>

 {!! \Livewire\Mechanisms\FrontendAssets\FrontendAssets::styles() !!}
 @vite(['resources/scss/app.scss', 'resources/js/app.js'])
 </head>
 <body class="min-h-screen bg-[color:var(--surface-page)] antialiased">
 <main class="min-h-screen w-full sm:flex sm:items-center sm:justify-center sm:px-6 sm:py-8" data-ui="register-shell">
 {{ $slot }}
 </main>

 {!! \Livewire\Mechanisms\FrontendAssets\FrontendAssets::scriptConfig() !!}
 </body>
</html>
