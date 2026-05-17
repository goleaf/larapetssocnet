<x-guest-layout>
 <div class="mb-6 space-y-2" data-ui="auth-form-header">
 <p class="chip min-h-8">Verify email</p>
 <h1 class="shell-title text-2xl">Check your inbox</h1>
 <p class="text-sm leading-6 shell-text-muted">
 {{ __('We sent a verification link to your email address. Verify it before continuing into your account.') }}
 </p>
 </div>

 @if (session('status') =='verification-link-sent')
 <x-ui.alert type="success" class="mb-4">
 {{ __('A new verification link has been sent to the email address you provided during registration.') }}
 </x-ui.alert>
 @endif

 <div class="rounded-[var(--radius-card)] border border-whisker/40 bg-cream/50 p-4" data-ui="email-verification-panel">
 <p class="text-sm leading-6 shell-text-muted">
 {{ __('If the message is not visible, check spam or send a new verification email.') }}
 </p>

 <div class="mt-4 flex flex-col-reverse gap-3 border-t border-whisker/30 pt-5 sm:flex-row sm:items-center sm:justify-between">
 <form method="POST" action="{{ route('logout') }}">
 @csrf
 <x-ui.button type="submit" variant="ghost" class="min-h-11 sm:min-w-28">{{ __('Log out') }}</x-ui.button>
 </form>

 <form method="POST" action="{{ route('verification.send') }}">
 @csrf
 <x-ui.button type="submit" variant="primary" class="min-h-11 sm:min-w-48">{{ __('Resend email') }}</x-ui.button>
 </form>
 </div>
 </div>
</x-guest-layout>
