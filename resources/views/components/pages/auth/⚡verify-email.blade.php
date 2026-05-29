<?php

use App\Models\Identity\User;
use App\Services\Auth\AuthAuditLogger;
use App\Services\Auth\AuthMailDispatcher;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts.auth-register')]
#[Title('Check your email')]
class extends Component
{
    public ?string $statusMessage = null;

    public ?string $errorMessage = null;

    public function mount(): void
    {
        $user = $this->currentUser();

        if ($user->hasVerifiedEmail()) {
            $this->redirectRoute($user->hasCompletedOnboarding() ? 'feed.index' : 'onboarding.show', navigate: false);
        }
    }

    public function resendVerificationEmail(AuthAuditLogger $auditLogger, AuthMailDispatcher $mailDispatcher): void
    {
        $user = $this->currentUser();

        if ($user->hasVerifiedEmail()) {
            $this->redirectRoute($user->hasCompletedOnboarding() ? 'feed.index' : 'onboarding.show', navigate: false);

            return;
        }

        $this->statusMessage = null;
        $this->errorMessage = null;

        $key = $this->rateLimitKey($user);

        if (RateLimiter::tooManyAttempts($key, 3)) {
            $this->errorMessage = 'You have requested too many verification emails. Please wait before trying again.';

            return;
        }

        RateLimiter::hit($key, 3600);

        if (! $mailDispatcher->queueVerificationEmail($user)) {
            $this->errorMessage = 'We could not send that email right now. Please try again later.';

            return;
        }

        $auditLogger->record($user, 'verification_email_resent', request());

        $this->statusMessage = 'Verification email sent — check your inbox and spam folder.';
        $this->dispatch('verification-resend-sent', seconds: 60);
        $this->dispatch('toast-message', message: $this->statusMessage, type: 'success');
    }

    public function maskedEmail(): string
    {
        $email = (string) $this->currentUser()->email;
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        if ($local === '' || $domain === '') {
            return $email;
        }

        return Str::limit($local, 2, '').'***@'.$domain;
    }

    private function currentUser(): User
    {
        $user = Auth::user();

        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function rateLimitKey(User $user): string
    {
        return 'verification-email-resend:user:'.$user->getKey();
    }
};
?>

<div
 class="flex min-h-screen w-full flex-col bg-[color:var(--surface-panel)] px-5 py-6 sm:min-h-0 sm:max-w-[33rem] sm:bg-transparent sm:px-0 sm:py-0"
 data-ui="email-verification-page"
 x-data="{
  remaining: 0,
  timer: null,
  start(seconds) {
   this.remaining = Number(seconds || 60);
   clearInterval(this.timer);
   this.timer = setInterval(() => {
    this.remaining = Math.max(0, this.remaining - 1);
    if (this.remaining === 0) {
     clearInterval(this.timer);
    }
   }, 1000);
  },
 }"
 x-on:verification-resend-sent.window="start($event.detail.seconds)"
>
 <header class="mx-auto w-full max-w-md pb-5 text-center sm:pb-6" data-ui="auth-form-header">
 <a href="/" class="inline-flex min-h-11 items-center justify-center gap-3 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
 <span class="inline-flex h-11 w-11 items-center justify-center rounded-[var(--radius-soft)] bg-paw-light text-xl text-paw-dark" aria-hidden="true">🐾</span>
 <span class="shell-title text-xl">{{ config('app.name', 'PetSocial') }}</span>
 </a>
 <h1 class="mt-4 shell-title text-2xl text-balance">Check your email</h1>
 <p class="mt-2 text-sm leading-6 shell-text-muted">
 We sent a verification link to <span class="font-semibold text-bark">{{ $this->maskedEmail() }}</span>.
 </p>
 </header>

 <section class="flex flex-1 flex-col bg-[color:var(--surface-panel)] sm:flex-none sm:rounded-[var(--radius-card)] sm:border sm:border-[var(--border-soft)] sm:px-6 sm:py-7" data-ui="email-verification-panel">
 <div class="flex flex-1 flex-col items-center justify-center gap-6 text-center">
 <div class="flex h-24 w-24 items-center justify-center rounded-[var(--radius-soft)] border border-paw/15 bg-paw-light text-paw-dark shadow-sm" aria-hidden="true">
 <svg class="h-12 w-12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
 <path d="M4 6.5h16v11H4z"/>
 <path d="m4.5 7 7.5 6 7.5-6"/>
 <path d="m4.5 17 5-4"/>
 <path d="m19.5 17-5-4"/>
 </svg>
 </div>

 <div class="space-y-3">
 <h2 class="shell-title text-xl">Verify your address to continue</h2>
 <p class="mx-auto max-w-sm text-sm leading-6 shell-text-muted">
 Email verification protects your account and confirms that security messages, password resets, and account alerts reach the right inbox.
 </p>
 </div>

 @if (session('status') === 'verification-link-expired')
 <x-ui.alert type="warning" class="w-full text-left">
 That verification link has expired. Request a new one below to continue.
 </x-ui.alert>
 @elseif (session('status') === 'verification-link-sent')
 <x-ui.alert type="success" class="w-full text-left">
 Verification email sent — check your inbox and spam folder.
 </x-ui.alert>
 @elseif (session('status') === 'verification-link-rate-limited')
 <x-ui.alert type="error" class="w-full text-left">
 You have requested too many verification emails. Please wait before trying again.
 </x-ui.alert>
 @elseif (session('status') === 'verification-link-failed')
 <x-ui.alert type="error" class="w-full text-left">
 We could not send that email right now. Please try again later.
 </x-ui.alert>
 @elseif (session('status') === 'account-reactivated')
 <x-ui.alert type="success" class="w-full text-left">
 Your account is active again. Verify your email to continue.
 </x-ui.alert>
 @endif

 <div class="w-full space-y-4">
 <button
 type="button"
 wire:click="resendVerificationEmail"
 wire:loading.attr="disabled"
 wire:target="resendVerificationEmail"
 x-bind:disabled="remaining > 0"
 x-bind:class="remaining > 0 ? 'cursor-not-allowed opacity-60' : ''"
 class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-[var(--radius-button)] bg-paw px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-paw-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw disabled:cursor-not-allowed disabled:opacity-60"
>
 <span wire:loading wire:target="resendVerificationEmail" class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white" aria-hidden="true"></span>
 <span wire:loading.remove wire:target="resendVerificationEmail">Resend verification email</span>
 <span wire:loading wire:target="resendVerificationEmail">Sending...</span>
 <span x-show="remaining > 0" x-cloak class="text-white/85" x-text="'(' + remaining + 's)'"></span>
 </button>

 @if ($statusMessage)
 <x-ui.alert type="success" class="w-full text-left">
 {{ $statusMessage }}
 </x-ui.alert>
 @endif

 @if ($errorMessage)
 <x-ui.alert type="error" class="w-full text-left">
 {{ $errorMessage }}
 </x-ui.alert>
 @endif

 <form method="POST" action="{{ route('logout') }}">
 @csrf
 <button type="submit" class="min-h-11 text-sm font-semibold text-paw underline-offset-4 hover:text-paw-dark hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
 Wrong email address? Log out
 </button>
 </form>
 </div>
 </div>
 </section>
</div>
