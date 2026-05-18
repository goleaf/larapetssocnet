<x-guest-layout>
 <div class="mb-6 space-y-2" data-ui="auth-form-header">
 <p class="chip min-h-8">Welcome back</p>
 <h1 class="shell-title text-2xl">Log in to your pet community</h1>
 <p class="text-sm leading-6 shell-text-muted">Use your email or username to continue.</p>
 </div>

 <x-auth-session-status class="mb-4" :status="session('status')"/>

 <div x-data="{ resetOpen: false, submitting: false }">
 <form method="POST" action="{{ route('login') }}" data-ui="login-form" @submit="submitting = true">
 @csrf

 <div class="space-y-4">
 <div>
 <x-ui.input id="email" type="text" name="email" label="Email or username" :value="old('email')" required autofocus autocomplete="username"/>
 </div>

 <div>
 <x-ui.input id="password" type="password" name="password" label="Password" required autocomplete="current-password"/>
 </div>

 <div class="block pt-1">
 <label for="remember_me" class="inline-flex min-h-11 cursor-pointer items-center rounded-[var(--radius-soft)] group focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-paw">
 <input id="remember_me" type="checkbox" class="rounded border-whisker/50 text-paw shadow-sm focus:ring-paw" name="remember">
 <span class="ms-2 text-sm text-fur transition-colors group-hover:text-bark">{{ __('Remember me') }}</span>
 </label>
 </div>

 <div class="flex flex-col-reverse gap-3 border-t border-whisker/30 pt-5 sm:flex-row sm:items-center sm:justify-between">
 @if (Route::has('password.request'))
 <button type="button" class="inline-flex min-h-11 items-center text-sm font-semibold text-paw hover:text-paw-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw" @click="resetOpen = ! resetOpen">
 {{ __('Forgot your password?') }}
 </button>
 @endif

 <x-ui.button type="submit" variant="primary" class="min-h-11 sm:min-w-32" x-bind:disabled="submitting">
 <span x-show="! submitting">{{ __('Log in') }}</span>
 <span x-show="submitting" x-cloak>{{ __('Logging in...') }}</span>
 </x-ui.button>
 </div>
 </div>
 </form>

 @if (Route::has('password.email'))
 <form
 method="POST"
 action="{{ route('password.email') }}"
 class="mt-4 overflow-hidden rounded-[var(--radius-card)] border border-whisker/40 bg-cream/50 p-4"
 x-show="resetOpen"
 x-cloak
 data-ui="inline-password-reset-form"
 >
 @csrf
 <div class="space-y-3">
 <p class="text-sm leading-6 shell-text-muted">
 {{ __('Enter your email and we will send a password reset link if an account exists.') }}
 </p>
 <x-ui.input id="reset_email" type="email" name="email" label="Email" :value="old('email')" required autocomplete="email"/>
 <x-ui.button type="submit" variant="secondary" class="min-h-11 sm:min-w-40">
 {{ __('Send reset link') }}
 </x-ui.button>
 </div>
 </form>
 @endif
 </div>
</x-guest-layout>
