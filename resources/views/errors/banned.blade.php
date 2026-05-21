<!DOCTYPE html>
<html lang="en">
<head>
 <meta charset="utf-8">
 <meta name="viewport" content="width=device-width, initial-scale=1">
 <title>Account Restricted</title>
 @vite(['resources/scss/app.scss'])
</head>
<body class="min-h-screen bg-gray-100 text-gray-900">
 <main class="mx-auto flex min-h-screen max-w-2xl items-center justify-center px-6">
 <div class="rounded-xl bg-white p-8 shadow-sm">
 <h1 class="text-2xl font-semibold">Account Restricted</h1>
 <p class="mt-3 text-sm text-gray-600">
 This account is currently restricted. If this seems incorrect, please contact support.
 </p>
 @if (auth()->check() && auth()->user()?->ban_reason)
 <p class="mt-3 text-sm text-gray-600">
 Reason: {{ auth()->user()->ban_reason }}
 </p>
 @endif
 <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center">
 @auth
 <form method="POST" action="{{ route('logout') }}">
 @csrf
 <button type="submit" class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
 Log out
 </button>
 </form>
 @else
 <a href="{{ route('login') }}" class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
 Back to Login
 </a>
 @endauth
 </div>
 </div>
 </main>
</body>
</html>
