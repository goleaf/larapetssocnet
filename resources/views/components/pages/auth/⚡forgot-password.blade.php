<?php

use App\Actions\Auth\RequestPasswordResetLinkAction;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts.auth-register')]
#[Title('Forgot password')]
class extends Component
{
    public string $resetEmail = '';

    public ?string $resetStatusMessage = null;

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
            source: 'forgot_password_page',
        );

        $this->dispatch('password-reset-link-sent');
    }
};
?>

<div
 class="flex min-h-screen w-full flex-col bg-[color:var(--surface-panel)] px-5 py-6 sm:min-h-0 sm:max-w-[33rem] sm:bg-transparent sm:px-0 sm:py-0"
 data-ui="forgot-password-page"
>
 <header class="mx-auto w-full max-w-md pb-5 text-center sm:pb-6" data-ui="auth-form-header">
 <a href="/" class="inline-flex min-h-11 items-center justify-center gap-3 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
 <span class="inline-flex h-11 w-11 items-center justify-center rounded-[var(--radius-soft)] bg-paw-light text-xl text-paw-dark" aria-hidden="true">🐾</span>
 <span class="shell-title text-xl">{{ config('app.name', 'PetSocial') }}</span>
 </a>
 <h1 class="mt-4 shell-title text-2xl text-balance">Reset access to your account</h1>
 <p class="mt-2 text-sm leading-6 shell-text-muted">Enter your email and we'll send a secure reset link.</p>
 </header>

 <section class="flex flex-1 flex-col bg-[color:var(--surface-panel)] sm:flex-none sm:rounded-[var(--radius-card)] sm:border sm:border-[var(--border-soft)] sm:px-6 sm:py-7" data-ui="forgot-password-card">
 @if (session('status'))
 <x-ui.alert type="info" class="mb-4">{{ session('status') }}</x-ui.alert>
 @endif

 @if ($resetStatusMessage)
 <x-ui.alert type="success" class="mb-4" data-ui="password-reset-request-status">
 {{ $resetStatusMessage }}
 </x-ui.alert>
 @endif

 <form wire:submit="sendPasswordResetLink" class="space-y-5" data-ui="password-email-form">
 <x-ui.input
  id="resetEmail"
  name="resetEmail"
  type="email"
  label="Email"
  required
  autofocus
  autocomplete="email"
  wire:model.live.debounce.400ms="resetEmail"
 />

 <div class="flex flex-col-reverse gap-3 border-t border-whisker/30 pt-5 sm:flex-row sm:items-center sm:justify-between">
 <a class="inline-flex min-h-11 items-center text-sm font-semibold text-paw hover:text-paw-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw" href="{{ route('login') }}">
 Back to login
 </a>

 <button
  type="submit"
  class="btn-base btn-primary h-[var(--control-height-md)] min-h-11 px-5 text-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw"
  wire:loading.class="pointer-events-none"
  wire:target="sendPasswordResetLink"
 >
 <span wire:loading.remove wire:target="sendPasswordResetLink">Send reset link</span>
 <span wire:loading.flex wire:target="sendPasswordResetLink" class="items-center gap-2">
 <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
 <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
 <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
 </svg>
 Sending...
 </span>
 </button>
 </div>
 </form>
 </section>
</div>
