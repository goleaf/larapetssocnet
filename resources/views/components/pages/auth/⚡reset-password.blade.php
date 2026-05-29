<?php

use App\Actions\Auth\ResetPasswordAction;
use App\Support\Auth\PasswordPolicy;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts.auth-register')]
#[Title('Reset password')]
class extends Component
{
    public string $token = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public bool $validLink = true;

    public function mount(string $token): void
    {
        $this->token = $token;

        $resetPassword = app(ResetPasswordAction::class);
        $record = $resetPassword->findTokenRecord($token);

        if ($record === null) {
            $this->validLink = false;
            session()->flash('status', ResetPasswordAction::INVALID_LINK_MESSAGE);
            $this->redirectRoute('password.request', navigate: false);

            return;
        }

        if ($resetPassword->tokenExpired($record)) {
            $this->validLink = false;
            session()->flash('status', ResetPasswordAction::EXPIRED_LINK_MESSAGE);
            $this->redirectRoute('password.request', navigate: false);

            return;
        }

        $this->email = Str::lower((string) $record->email);
    }

    public function resetPassword(ResetPasswordAction $resetPassword): void
    {
        $validated = $this->validate([
            'email' => ['required', 'email'],
            'password' => PasswordPolicy::validationRules(),
            'password_confirmation' => ['required'],
        ], [
            'password_confirmation.required' => 'Confirm your password.',
            ...PasswordPolicy::validationMessages(),
        ]);

        $resetPassword->reset(
            token: $this->token,
            email: (string) $validated['email'],
            password: (string) $validated['password'],
            request: request(),
        );

        session()->flash('status', ResetPasswordAction::SUCCESS_MESSAGE);

        $this->redirectRoute('login', navigate: false);
    }

    /**
     * @return array<int, string>
     */
    public function commonPasswordHashes(): array
    {
        return collect(config('common_passwords.passwords', []))
            ->map(fn (string $password): string => hash('sha256', Str::lower($password)))
            ->values()
            ->all();
    }
};
?>

<div
 class="flex min-h-screen w-full flex-col bg-[color:var(--surface-panel)] px-5 py-6 sm:min-h-0 sm:max-w-[36rem] sm:bg-transparent sm:px-0 sm:py-0"
 data-ui="reset-password-page"
 x-data="passwordCredentialForm({
  commonPasswordHashes: @js($this->commonPasswordHashes()),
 })"
>
 <header class="mx-auto w-full max-w-md pb-5 text-center sm:pb-6" data-ui="auth-form-header">
 <a href="/" class="inline-flex min-h-11 items-center justify-center gap-3 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
 <span class="inline-flex h-11 w-11 items-center justify-center rounded-[var(--radius-soft)] bg-paw-light text-xl text-paw-dark" aria-hidden="true">🐾</span>
 <span class="shell-title text-xl">{{ config('app.name', 'PetSocial') }}</span>
 </a>
 <h1 class="mt-4 shell-title text-2xl text-balance">Create a new password</h1>
 <p class="mt-2 text-sm leading-6 shell-text-muted">Use a fresh password to keep your pet community account protected.</p>
 </header>

 <section class="flex flex-1 flex-col bg-[color:var(--surface-panel)] sm:flex-none sm:rounded-[var(--radius-card)] sm:border sm:border-[var(--border-soft)] sm:px-6 sm:py-7" data-ui="reset-password-card">
 @if ($validLink)
 <form wire:submit="resetPassword" class="flex flex-1 flex-col" data-ui="password-reset-form">
 <div class="flex-1 space-y-5">
 <x-ui.input
  id="email"
  name="email"
  type="email"
  label="Email"
  required
  readonly
  autocomplete="username"
  wire:model="email"
 />

 <div>
 <x-ui.input
  id="password"
  name="password"
  type="password"
  label="New password"
  required
  autocomplete="new-password"
  wire:model.live.debounce.200ms="password"
  x-model="password"
  x-on:input.debounce.200ms="updatePasswordStrength()"
 />
 <div x-show="password.length > 0" x-cloak x-transition class="mt-3 space-y-1.5" data-ui="password-strength-meter">
 <div class="grid grid-cols-4 gap-1.5" aria-hidden="true">
 <template x-for="segment in [1, 2, 3, 4]" :key="segment">
 <span class="h-1.5 rounded-full transition-colors duration-200" x-bind:class="segmentClass(segment)"></span>
 </template>
 </div>
 <p class="text-xs text-fur">Strength: <span class="font-semibold text-bark" x-text="passwordLevel"></span></p>
 </div>
 </div>

 <div class="flex flex-col gap-1">
 <x-ui.label for="password_confirmation" required>Confirm password</x-ui.label>
 <div class="relative">
 <input
  id="password_confirmation"
  name="password_confirmation"
  type="password"
  required
  autocomplete="new-password"
  class="form-input h-[var(--control-height-md)] w-full pr-10 text-sm @error('password_confirmation') border-rose text-rose focus:border-rose @else focus:border-paw @enderror"
  wire:model.live.debounce.200ms="password_confirmation"
  x-model="passwordConfirmation"
  @error('password_confirmation') aria-invalid="true" @enderror
 >
 <div x-show="passwordsMatch" x-cloak x-transition.opacity class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-success" aria-hidden="true">
 <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
 <path d="M20 6 9 17l-5-5"></path>
 </svg>
 </div>
 <div x-show="passwordMismatch" x-cloak x-transition.opacity class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-rose" aria-hidden="true">
 <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
 <path d="M18 6 6 18"></path>
 <path d="m6 6 12 12"></path>
 </svg>
 </div>
 </div>
 @error('password_confirmation')
 <x-ui.hint :error="$message"/>
 @enderror
 </div>

 @error('password')
 <x-ui.hint :error="$message"/>
 @enderror
 </div>

 <div class="mt-6 flex flex-col-reverse gap-3 border-t border-whisker/30 pt-5 sm:flex-row sm:items-center sm:justify-between">
 <a class="inline-flex min-h-11 items-center text-sm font-semibold text-paw hover:text-paw-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw" href="{{ route('login') }}">
 Back to login
 </a>

 <button
  type="submit"
  class="btn-base btn-primary h-[var(--control-height-md)] min-h-11 px-5 text-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw"
  x-bind:class="{ 'cursor-not-allowed opacity-60': formInvalid }"
  x-bind:aria-disabled="formInvalid ? 'true' : 'false'"
  wire:loading.class="pointer-events-none"
  wire:target="resetPassword"
 >
 <span wire:loading.remove wire:target="resetPassword">Reset password</span>
 <span wire:loading.flex wire:target="resetPassword" class="items-center gap-2">
 <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
 <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
 <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
 </svg>
 Updating...
 </span>
 </button>
 </div>
 </form>
 @endif
 </section>
</div>
