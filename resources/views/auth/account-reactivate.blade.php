<x-guest-layout>
 <div class="mb-6 space-y-2" data-ui="auth-form-header">
 <p class="chip min-h-8">Reactivate</p>
 <h1 class="shell-title text-2xl">Reactivate your account</h1>
 <p class="text-sm leading-6 shell-text-muted">
 {{ __('Your account is deactivated. Confirm your password to reactivate it before entering PetSocial.') }}
 </p>
 </div>

 @if ($user->deactivation_reason)
 <x-ui.alert type="warning" class="mb-4">
 {{ __('Reason: :reason', ['reason' => $user->deactivation_reason]) }}
 </x-ui.alert>
 @endif

 <div class="rounded-[var(--radius-card)] border border-whisker/40 bg-cream/50 p-4" data-ui="account-reactivation-panel">
 <form method="POST" action="{{ route('account.reactivate') }}" class="space-y-4">
 @csrf
 <x-ui.input id="password" type="password" name="password" label="Confirm password" required autocomplete="current-password"/>

 <div class="flex justify-end border-t border-whisker/30 pt-5">
 <x-ui.button type="submit" variant="primary" class="min-h-11 sm:min-w-48">
 {{ __('Reactivate account') }}
 </x-ui.button>
 </div>
 </form>

 <form method="POST" action="{{ route('logout') }}" class="mt-3">
 @csrf
 <x-ui.button type="submit" variant="ghost" class="min-h-11 sm:min-w-28">{{ __('Log out') }}</x-ui.button>
 </form>
 </div>
</x-guest-layout>
