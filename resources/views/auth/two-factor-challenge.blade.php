<x-guest-layout>
 <div class="mb-6 space-y-2" data-ui="auth-form-header">
 <p class="chip min-h-8">Two-factor check</p>
 <h1 class="shell-title text-2xl">Confirm it is you</h1>
 <p class="text-sm leading-6 shell-text-muted">Enter a code from your authenticator app or use one recovery code.</p>
 </div>

 <form
 method="POST"
 action="{{ route('two-factor.challenge.store') }}"
 class="space-y-4"
 data-ui="two-factor-challenge-form"
 x-data="{ useRecovery: false, submitting: false }"
 @submit="submitting = true"
 >
 @csrf

 <div x-show="! useRecovery" x-transition>
 <x-ui.input
  id="code"
  name="code"
  type="text"
  label="Authentication code"
  inputmode="numeric"
  pattern="[0-9]*"
  autocomplete="one-time-code"
  autofocus
 />
 </div>

 <div x-show="useRecovery" x-cloak x-transition>
 <x-ui.input
  id="recovery_code"
  name="recovery_code"
  type="text"
  label="Recovery code"
  autocomplete="one-time-code"
 />
 </div>

 <button
 type="button"
 class="inline-flex min-h-11 items-center text-sm font-semibold text-paw hover:text-paw-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw"
 @click="useRecovery = ! useRecovery"
 >
 <span x-show="! useRecovery">Use a recovery code</span>
 <span x-show="useRecovery" x-cloak>Use an authenticator code</span>
 </button>

 <div class="flex justify-end border-t border-whisker/30 pt-5">
 <x-ui.button type="submit" variant="primary" class="min-h-11 sm:min-w-40" x-bind:disabled="submitting">
 <span x-show="! submitting">{{ __('Confirm access') }}</span>
 <span x-show="submitting" x-cloak>{{ __('Checking...') }}</span>
 </x-ui.button>
 </div>
 </form>

 <form method="POST" action="{{ route('logout') }}" class="mt-3">
 @csrf
 <button type="submit" class="inline-flex min-h-11 items-center text-sm font-semibold text-paw hover:text-paw-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
 {{ __('Log out') }}
 </button>
 </form>
</x-guest-layout>
