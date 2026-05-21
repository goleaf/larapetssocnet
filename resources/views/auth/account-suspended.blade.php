<x-guest-layout>
 <div class="mb-6 space-y-2" data-ui="auth-form-header">
 <p class="chip min-h-8">Suspended</p>
 <h1 class="shell-title text-2xl">Account temporarily suspended</h1>
 <p class="text-sm leading-6 shell-text-muted">
 {{ __('Your account cannot access PetSocial while the suspension is active.') }}
 </p>
 </div>

 @if (auth()->user()?->suspended_until)
 <x-ui.alert type="warning" class="mb-4">
 {{ __('Suspension ends :date.', ['date' => auth()->user()->suspended_until->toFormattedDayDateString()]) }}
 </x-ui.alert>
 @endif

 @if (auth()->user()?->suspension_reason)
 <p class="mb-4 text-sm leading-6 shell-text-muted">
 {{ __('Reason: :reason', ['reason' => auth()->user()->suspension_reason]) }}
 </p>
 @endif

 <form method="POST" action="{{ route('logout') }}">
 @csrf
 <x-ui.button type="submit" variant="primary" class="min-h-11 sm:min-w-28">{{ __('Log out') }}</x-ui.button>
 </form>
</x-guest-layout>
