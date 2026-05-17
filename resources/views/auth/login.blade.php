<x-guest-layout>
 <div class="mb-6 space-y-2" data-ui="auth-form-header">
 <p class="chip min-h-8">Welcome back</p>
 <h1 class="shell-title text-2xl">Log in to your pet community</h1>
 <p class="text-sm leading-6 shell-text-muted">Use your email or username to continue.</p>
 </div>

 <x-auth-session-status class="mb-4" :status="session('status')"/>

 <form method="POST" action="{{ route('login') }}" data-ui="login-form">
 @csrf

 <div class="space-y-4">
 <div>
 <x-ui.input id="email" type="text" name="email" label="Email or Username" :value="old('email')" required autofocus
 autocomplete="username"/>
 </div>

 <div>
 <x-ui.input id="password" type="password" name="password" label="Password" required
 autocomplete="current-password"/>
 </div>

 <div class="block pt-1">
 <label for="remember_me" class="inline-flex min-h-11 items-center group cursor-pointer rounded-[var(--radius-soft)] focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-paw">
 <input id="remember_me" type="checkbox"
 class="rounded border-whisker/50 text-paw shadow-sm focus:ring-paw" name="remember">
 <span
 class="ms-2 text-sm text-fur group-hover:text-bark transition-colors">{{ __('Remember me') }}</span>
 </label>
 </div>

 <div class="flex flex-col-reverse gap-3 border-t border-whisker/30 pt-5 sm:flex-row sm:items-center sm:justify-between">
 @if (Route::has('password.request'))
 <a class="inline-flex min-h-11 items-center text-sm font-semibold text-paw hover:text-paw-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw" href="{{ route('password.request') }}">
 {{ __('Forgot your password?') }}
 </a>
 @endif

 <x-ui.button type="submit" variant="primary" class="min-h-11 sm:min-w-32">
 {{ __('Log in') }}
 </x-ui.button>
 </div>
 </div>
 </form>

 @if (($loginUsers ?? collect())->isNotEmpty())
 <section class="mt-5 rounded-[var(--radius-card)] border border-whisker/40 bg-cream/50 p-4" data-ui="quick-login-panel">
 <div class="flex items-center justify-between gap-3">
 <p class="text-sm font-semibold text-bark">Quick Login Users</p>
 <code class="rounded-[var(--radius-soft)] bg-warm-white px-2 py-1 text-xs font-semibold text-paw">password</code>
 </div>
 <p class="mt-1 text-xs text-fur">Use username (or email) and the shared password above.</p>

 <ul class="mt-3 max-h-64 space-y-2 overflow-y-auto pr-1">
 @forelse ($loginUsers as $loginUser)
 <li class="rounded-[var(--radius-soft)] border border-whisker/30 bg-warm-white/85 px-3 py-2">
 <p class="truncate text-sm font-semibold text-bark">
 {{ $loginUser->username ? '@'.$loginUser->username : $loginUser->email }}
 </p>
 <p class="truncate text-xs text-fur">{{ $loginUser->email }}</p>
 </li>
 @empty
 <li class="rounded-[var(--radius-soft)] border border-whisker/30 bg-warm-white/85 px-3 py-2 text-xs text-fur">
 No users found.
 </li>
 @endforelse
 </ul>
 </section>
 @endif
</x-guest-layout>
