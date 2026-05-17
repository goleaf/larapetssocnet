<x-guest-layout>
 <div class="mb-6 space-y-2" data-ui="auth-form-header">
 <p class="chip min-h-8">Secure area</p>
 <h1 class="shell-title text-2xl">Confirm your password</h1>
 <p class="text-sm leading-6 shell-text-muted">
 {{ __('Confirm your password before continuing to this protected area.') }}
 </p>
 </div>

 <form method="POST" action="{{ route('password.confirm') }}" class="space-y-4" data-ui="password-confirm-form">
 @csrf

 <x-ui.input id="password" type="password" name="password" :label="__('Password')" required autocomplete="current-password"/>

 <div class="flex justify-end border-t border-whisker/30 pt-5">
 <x-ui.button type="submit" variant="primary" class="min-h-11 sm:min-w-32">
 {{ __('Confirm') }}
 </x-ui.button>
 </div>
 </form>
</x-guest-layout>
