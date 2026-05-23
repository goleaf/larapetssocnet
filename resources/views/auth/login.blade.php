<x-guest-layout>
 @php
 $socialProviders = collect([
  'google' => 'Google',
  'facebook' => 'Facebook',
 ])
  ->filter(fn (string $label, string $provider): bool => filled(config("services.{$provider}.client_id")) && filled(config("services.{$provider}.client_secret")));
 @endphp

 <div class="mb-6 space-y-2" data-ui="auth-form-header">
 <p class="chip min-h-8">Welcome back</p>
 <h1 class="shell-title text-2xl">Log in to your pet community</h1>
 <p class="text-sm leading-6 shell-text-muted">Use your email or username to continue.</p>
 </div>

 <x-auth-session-status class="mb-4" :status="session('status')"/>

 @if ($socialProviders->isNotEmpty())
 <div class="mb-4 grid gap-2" data-ui="social-login-actions">
 @foreach ($socialProviders as $provider => $label)
 <x-ui.button
  :href="route('social.redirect', ['provider' => $provider])"
  variant="secondary"
  class="min-h-11 justify-center"
  data-ui="social-login-{{ $provider }}"
 >
  Continue with {{ $label }}
 </x-ui.button>
 @endforeach
 </div>
 <div class="mb-4 flex items-center gap-3 text-xs uppercase tracking-[0.08em] text-fur">
 <span class="h-px flex-1 bg-whisker/40"></span>
 <span>or</span>
 <span class="h-px flex-1 bg-whisker/40"></span>
 </div>
 @endif

 <div x-data="{ resetOpen: false, magicOpen: false, submitting: false, magicSubmitting: false }">
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
 <input id="remember_me" type="checkbox" class="rounded border-whisker/50 text-paw shadow-sm focus:ring-paw" name="remember" value="1">
 <span class="ms-2 text-sm text-fur transition-colors group-hover:text-bark">{{ __('Remember me') }}</span>
 </label>
 </div>

 <div class="flex flex-col-reverse gap-3 border-t border-whisker/30 pt-5 sm:flex-row sm:items-center sm:justify-between">
 <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
 @if (Route::has('password.request'))
 <button type="button" class="inline-flex min-h-11 items-center text-sm font-semibold text-paw hover:text-paw-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw" @click="resetOpen = ! resetOpen">
 {{ __('Forgot your password?') }}
 </button>
 @endif

 @if (Route::has('magic-login.store'))
 <button type="button" class="inline-flex min-h-11 items-center text-sm font-semibold text-paw hover:text-paw-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw" @click="magicOpen = ! magicOpen">
 {{ __('Email me a sign-in link') }}
 </button>
 @endif
 </div>

 <x-ui.button type="submit" variant="primary" class="min-h-11 sm:min-w-32" x-bind:disabled="submitting">
 <span x-show="! submitting">{{ __('Log in') }}</span>
 <span x-show="submitting" x-cloak>{{ __('Logging in...') }}</span>
 </x-ui.button>
 </div>
 </div>
 </form>

 @if (Route::has('magic-login.store'))
 <form
 method="POST"
 action="{{ route('magic-login.store') }}"
 class="mt-4 overflow-hidden rounded-[var(--radius-card)] border border-whisker/40 bg-cream/50 p-4"
 x-show="magicOpen"
 x-cloak
 x-transition
 data-ui="magic-link-request-form"
 @submit="magicSubmitting = true"
 >
 @csrf
 <div class="space-y-3">
 <p class="text-sm leading-6 shell-text-muted">
 {{ __('We will send a one-time sign-in link if this email belongs to an account.') }}
 </p>
 <x-ui.input id="magic_email" type="email" name="email" label="Email" :value="old('email')" required autocomplete="email"/>
 <x-ui.button type="submit" variant="secondary" class="min-h-11 sm:min-w-40" x-bind:disabled="magicSubmitting">
 <span x-show="! magicSubmitting">{{ __('Send sign-in link') }}</span>
 <span x-show="magicSubmitting" x-cloak>{{ __('Sending link...') }}</span>
 </x-ui.button>
 </div>
 </form>
 @endif

 @if (Route::has('password.email'))
 <form
 method="POST"
 action="{{ route('password.email') }}"
 class="mt-4 overflow-hidden rounded-[var(--radius-card)] border border-whisker/40 bg-cream/50 p-4"
 x-show="resetOpen"
 x-cloak
 x-transition
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
