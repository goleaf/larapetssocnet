<?php

use App\Actions\Auth\AuthenticateUserAction;
use App\Actions\Auth\AuthenticationResult;
use App\Actions\Auth\RequestMagicLoginLinkAction;
use App\Actions\Auth\RequestPasswordResetLinkAction;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts.auth-register')]
#[Title('Log in')]
class extends Component
{
    public string $credential = '';

    public string $password = '';

    public bool $remember = false;

    public string $resetEmail = '';

    public ?string $resetStatusMessage = null;

    public string $magicEmail = '';

    public ?string $magicStatusMessage = null;

    public ?string $lockoutMessage = null;

    public int $lockoutSeconds = 0;

    public function authenticate(AuthenticateUserAction $authenticate): void
    {
        $this->resetErrorBag();
        $this->lockoutMessage = null;

        $validated = $this->validate([
            'credential' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:1024'],
            'remember' => ['boolean'],
        ], [
            'credential.required' => 'Email or username is required.',
            'password.required' => 'Password is required.',
        ]);

        $result = $authenticate->handle(
            credential: (string) $validated['credential'],
            password: (string) $validated['password'],
            remember: (bool) $validated['remember'],
            request: request(),
        );

        if ($result->failed()) {
            $this->addError('credential', (string) $result->message);

            if ($result->lockoutSeconds !== null) {
                $this->lockoutSeconds = $result->lockoutSeconds;
                $this->lockoutMessage = $result->message;
                $this->dispatch('login-lockout-started', seconds: $result->lockoutSeconds);
            }

            return;
        }

        session()->regenerate();

        $this->redirect($this->redirectPathFor($result, request()), navigate: false);
    }

    public function sendPasswordResetLink(RequestPasswordResetLinkAction $action): void
    {
        $this->resetErrorBag('resetEmail');
        $this->resetStatusMessage = null;

        $validated = $this->validate([
            'resetEmail' => ['required', 'email'],
        ], [
            'resetEmail.required' => 'Email is required.',
            'resetEmail.email' => 'Enter a valid email address.',
        ]);

        $this->resetEmail = Str::lower(trim((string) $validated['resetEmail']));
        $this->resetStatusMessage = $action->handle(
            email: $this->resetEmail,
            request: request(),
            source: 'inline_login_panel',
        );

        $this->dispatch('password-reset-link-sent');
    }

    public function sendMagicLoginLink(RequestMagicLoginLinkAction $action): void
    {
        $this->resetErrorBag('magicEmail');
        $this->magicStatusMessage = null;

        $validated = $this->validate([
            'magicEmail' => ['required', 'email'],
        ], [
            'magicEmail.required' => 'Email is required.',
            'magicEmail.email' => 'Enter a valid email address.',
        ]);

        $this->magicEmail = Str::lower(trim((string) $validated['magicEmail']));
        $this->magicStatusMessage = $action->handle(
            email: $this->magicEmail,
            request: request(),
            source: 'inline_login_panel',
        );

        $this->dispatch('magic-login-link-sent');
    }

    /**
     * @return array<string, string>
     */
    public function socialProviders(): array
    {
        return collect([
            'google' => 'Google',
            'facebook' => 'Facebook',
        ])
            ->filter(fn (string $label, string $provider): bool => filled(config("services.{$provider}.client_id")) && filled(config("services.{$provider}.client_secret")))
            ->all();
    }

    private function redirectPathFor(AuthenticationResult $result, Request $request): string
    {
        if ($result->redirectRoute !== null) {
            return route($result->redirectRoute, absolute: false);
        }

        if ($result->requiresTwoFactor) {
            return route('two-factor.challenge', absolute: false);
        }

        if (! $result->user?->hasVerifiedEmail()) {
            return route('verification.notice', absolute: false);
        }

        if (! $result->user->hasCompletedOnboarding()) {
            return route('onboarding.show', absolute: false);
        }

        return $this->safeIntendedPath($request);
    }

    private function safeIntendedPath(Request $request): string
    {
        $fallback = route('feed.index', absolute: false);
        $intended = session()->pull('url.intended');

        if (! is_string($intended) || $intended === '') {
            return $fallback;
        }

        if (Str::startsWith($intended, '/') && ! Str::startsWith($intended, '//')) {
            return $intended;
        }

        $host = parse_url($intended, PHP_URL_HOST);
        $scheme = parse_url($intended, PHP_URL_SCHEME);

        if ($host === $request->getHost() && in_array($scheme, ['http', 'https'], true)) {
            return $intended;
        }

        return $fallback;
    }
};
?>

<div
 class="flex min-h-screen w-full flex-col bg-[color:var(--surface-panel)] px-5 py-6 sm:min-h-0 sm:max-w-[33rem] sm:bg-transparent sm:px-0 sm:py-0"
 data-ui="login-page"
 x-data="{
 resetOpen: false,
 resetPanelHeight: '0px',
  magicOpen: false,
  magicPanelHeight: '0px',
  lockoutRemaining: @js($lockoutSeconds),
  lockoutTimer: null,
  get locked() {
   return this.lockoutRemaining > 0;
  },
  get lockoutCountdown() {
   const minutes = Math.floor(this.lockoutRemaining / 60);
   const seconds = String(this.lockoutRemaining % 60).padStart(2, '0');

   return `${minutes}:${seconds}`;
  },
  toggleReset() {
   this.resetOpen = ! this.resetOpen;
   if (this.resetOpen) {
    this.magicOpen = false;
   }
   this.$nextTick(() => this.refreshPanels());
  },
  toggleMagic() {
   this.magicOpen = ! this.magicOpen;
   if (this.magicOpen) {
    this.resetOpen = false;
   }
   this.$nextTick(() => this.refreshPanels());
  },
  refreshResetPanel() {
   if (! this.$refs.resetPanel) {
    return;
   }

   this.resetPanelHeight = this.resetOpen ? `${this.$refs.resetPanel.scrollHeight}px` : '0px';
  },
  refreshMagicPanel() {
   if (! this.$refs.magicPanel) {
    return;
   }

   this.magicPanelHeight = this.magicOpen ? `${this.$refs.magicPanel.scrollHeight}px` : '0px';
  },
  refreshPanels() {
   this.refreshResetPanel();
   this.refreshMagicPanel();
  },
  startLockout(seconds) {
   this.lockoutRemaining = Number(seconds || 0);
   clearInterval(this.lockoutTimer);

   if (this.lockoutRemaining <= 0) {
    return;
   }

   this.lockoutTimer = setInterval(() => {
    this.lockoutRemaining = Math.max(0, this.lockoutRemaining - 1);

    if (this.lockoutRemaining === 0) {
     clearInterval(this.lockoutTimer);
    }
   }, 1000);
  },
 }"
 x-init="$nextTick(() => refreshPanels()); if (lockoutRemaining > 0) startLockout(lockoutRemaining)"
 x-on:resize.window="refreshPanels()"
 x-on:login-lockout-started.window="startLockout($event.detail.seconds)"
 x-on:password-reset-link-sent.window="$nextTick(() => refreshPanels())"
 x-on:magic-login-link-sent.window="$nextTick(() => refreshPanels())"
>
 <header class="mx-auto w-full max-w-md pb-5 text-center sm:pb-6" data-ui="auth-form-header">
 <a href="/" class="inline-flex min-h-11 items-center justify-center gap-3 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
 <span class="inline-flex h-11 w-11 items-center justify-center rounded-[var(--radius-soft)] bg-paw-light text-xl text-paw-dark" aria-hidden="true">🐾</span>
 <span class="shell-title text-xl">{{ config('app.name', 'PetSocial') }}</span>
 </a>
 <h1 class="mt-4 shell-title text-2xl text-balance">Log in to your pet community</h1>
 <p class="mt-2 text-sm leading-6 shell-text-muted">Use your email or username to continue.</p>
 </header>

 <section class="flex flex-1 flex-col bg-[color:var(--surface-panel)] sm:flex-none sm:rounded-[var(--radius-card)] sm:border sm:border-[var(--border-soft)] sm:px-6 sm:py-7" data-ui="login-card">
 @if (session('status'))
 <x-auth-session-status class="mb-4" :status="session('status')"/>
 @endif

 @if ($this->socialProviders() !== [])
 <div class="mb-4 grid gap-2" data-ui="social-login-actions">
 @foreach ($this->socialProviders() as $provider => $label)
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
 @endif

 <div class="mb-4 text-center" data-ui="magic-login-option">
 <a
  href="#inline-magic-login-panel"
  class="inline-flex min-h-11 items-center justify-center text-sm font-semibold text-paw hover:text-paw-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw"
  x-on:click.prevent="toggleMagic()"
  x-bind:aria-expanded="magicOpen.toString()"
  aria-controls="inline-magic-login-panel"
 >
 <span x-text="magicOpen ? 'Cancel login link' : 'Send me a login link'">Send me a login link</span>
 </a>
 </div>

 <div
  id="inline-magic-login-panel"
  x-ref="magicPanel"
  x-bind:style="{ height: magicPanelHeight }"
  class="mb-4 overflow-hidden transition-[height] duration-300 ease-out motion-reduce:transition-none"
  data-ui="inline-magic-login-form"
 >
 <form wire:submit="sendMagicLoginLink" class="rounded-[var(--radius-card)] border border-whisker/40 bg-cream/50 p-4">
 <div class="space-y-3">
 <p class="text-sm leading-6 shell-text-muted">
 Enter your email and we'll send a link that signs you in once.
 </p>

 <x-ui.input
  id="magicEmail"
  name="magicEmail"
  type="email"
  label="Email address"
  required
  autocomplete="email"
  wire:model="magicEmail"
 />

 <x-ui.button type="submit" variant="secondary" class="min-h-11 sm:min-w-40" wire:loading.attr="disabled" wire:target="sendMagicLoginLink">
 <span wire:loading.remove wire:target="sendMagicLoginLink">Send login link</span>
 <span wire:loading.flex wire:target="sendMagicLoginLink" class="items-center gap-2">
 <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
 <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
 <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
 </svg>
 Sending...
 </span>
 </x-ui.button>

 @if ($magicStatusMessage)
 <x-ui.alert type="success" data-ui="magic-login-inline-status">
 {{ $magicStatusMessage }}
 </x-ui.alert>
 @endif
 </div>
 </form>
 </div>

 <div class="mb-4 flex items-center gap-3 text-xs uppercase tracking-[0.08em] text-fur">
 <span class="h-px flex-1 bg-whisker/40"></span>
 <span>or</span>
 <span class="h-px flex-1 bg-whisker/40"></span>
 </div>

 <form wire:submit="authenticate" data-ui="login-form" class="space-y-5">
 <x-ui.input
  id="credential"
  name="credential"
  type="text"
  label="Email or username"
  placeholder="Email or username"
  required
  autofocus
  autocomplete="username"
  wire:model="credential"
  x-bind:disabled="locked"
 />

 <x-ui.input
  id="password"
  name="password"
  type="password"
  label="Password"
  required
  autocomplete="current-password"
  wire:model="password"
  x-bind:disabled="locked"
 />

 <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
 <label for="remember" class="inline-flex min-h-11 cursor-pointer items-center rounded-[var(--radius-soft)] group focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-paw">
 <input
  id="remember"
  type="checkbox"
  class="rounded border-whisker/50 text-paw shadow-sm focus:ring-paw"
  wire:model="remember"
  x-bind:disabled="locked"
 >
 <span class="ms-2 text-sm text-fur transition-colors group-hover:text-bark">Remember me</span>
 </label>

 <button
  type="button"
  class="inline-flex min-h-11 items-center text-sm font-semibold text-paw hover:text-paw-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw"
  x-on:click="toggleReset()"
  x-bind:aria-expanded="resetOpen.toString()"
  aria-controls="inline-password-reset-panel"
 >
 <span x-text="resetOpen ? 'Cancel' : 'Forgot password?'">Forgot password?</span>
 </button>
 </div>

 @if ($lockoutMessage)
 <x-ui.alert type="warning" data-ui="login-lockout-message">
 {{ $lockoutMessage }}
 </x-ui.alert>
 @endif

 <div class="flex flex-col-reverse gap-3 border-t border-whisker/30 pt-5 sm:flex-row sm:items-center sm:justify-between">
 <a class="inline-flex min-h-11 items-center text-sm font-semibold text-paw hover:text-paw-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw" href="{{ route('register') }}">
 Create an account
 </a>

 <button
  type="submit"
  class="btn-base btn-primary h-[var(--control-height-md)] min-h-11 px-5 text-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw"
  x-bind:disabled="locked"
  x-bind:class="{ 'cursor-not-allowed opacity-60': locked }"
  wire:loading.class="pointer-events-none"
  wire:target="authenticate"
 >
 <span wire:loading.remove wire:target="authenticate" x-show="! locked">Log in</span>
 <span wire:loading.remove wire:target="authenticate" x-show="locked" x-cloak>Try again in <span x-text="lockoutCountdown"></span></span>
 <span wire:loading.flex wire:target="authenticate" class="items-center gap-2">
 <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
 <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
 <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
 </svg>
 Logging in...
 </span>
 </button>
 </div>
 </form>

 <div
  id="inline-password-reset-panel"
  x-ref="resetPanel"
  x-bind:style="{ height: resetPanelHeight }"
  class="mt-4 overflow-hidden transition-[height] duration-300 ease-out motion-reduce:transition-none"
  data-ui="inline-password-reset-form"
 >
 <form wire:submit="sendPasswordResetLink" class="rounded-[var(--radius-card)] border border-whisker/40 bg-cream/50 p-4">
 <div class="space-y-3">
 <p class="text-sm leading-6 shell-text-muted">
 Enter your email and we'll send a link to reset your password.
 </p>

 <x-ui.input
  id="resetEmail"
  name="resetEmail"
  type="email"
  label="Email"
  required
  autocomplete="email"
  wire:model="resetEmail"
 />

 <x-ui.button type="submit" variant="secondary" class="min-h-11 sm:min-w-40" wire:loading.attr="disabled" wire:target="sendPasswordResetLink">
 <span wire:loading.remove wire:target="sendPasswordResetLink">Send reset link</span>
 <span wire:loading.flex wire:target="sendPasswordResetLink" class="items-center gap-2">
 <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
 <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
 <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
 </svg>
 Sending...
 </span>
 </x-ui.button>

 @if ($resetStatusMessage)
 <x-ui.alert type="success" data-ui="password-reset-inline-status">
 {{ $resetStatusMessage }}
 </x-ui.alert>
 @endif
 </div>
 </form>
 </div>
 </section>
</div>
