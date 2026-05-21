<x-guest-layout>
 <div class="mb-6 space-y-2" data-ui="auth-form-header">
 <p class="chip min-h-8">Deletion pending</p>
 <h1 class="shell-title text-2xl">Confirm your account return</h1>
 <p class="text-sm leading-6 shell-text-muted">
 {{ __('Your account is scheduled for deletion. Confirm your password to cancel deletion before returning to PetSocial.') }}
 </p>
 </div>

 @if ($user->scheduled_deletion_at)
 <x-ui.alert type="warning" class="mb-4">
 {{ __('Deletion is scheduled for :date.', ['date' => $user->scheduled_deletion_at->toFormattedDayDateString()]) }}
 </x-ui.alert>
 @endif

 <div class="rounded-[var(--radius-card)] border border-whisker/40 bg-cream/50 p-4" data-ui="account-deletion-pending-panel">
 <form method="POST" action="{{ route('account.cancel-deletion') }}" id="cancel-deletion-form" class="space-y-4">
 @csrf
 <x-ui.input id="password" type="password" name="password" label="Confirm password" required autocomplete="current-password"/>

 <div class="flex justify-end border-t border-whisker/30 pt-5">
 <x-ui.button type="submit" variant="primary" class="min-h-11 sm:min-w-48">
 {{ __('Cancel deletion') }}
 </x-ui.button>
 </div>
 </form>

 <form method="POST" action="{{ route('logout') }}" class="mt-3">
 @csrf
 <x-ui.button type="submit" variant="ghost" class="min-h-11 sm:min-w-28">{{ __('Log out') }}</x-ui.button>
 </form>
 </div>
</x-guest-layout>
