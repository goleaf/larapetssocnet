<x-guest-layout>
 <x-ui.alert type="info" class="mb-4">
 {{ __('en.forgot_your_password_no_problem_just_let_us_know_your_email_address_and_we_will') }}
 </x-ui.alert>

 @if (session('status'))
 <x-ui.alert type="success" class="mb-4">{{ session('status') }}</x-ui.alert>
 @endif

 <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
 @csrf

 <x-ui.input
 name="email"
 label="{{ __('en.email') }}"
 type="email"
 :value="old('email')"
 required
 autofocus
  />

 <div class="flex items-center justify-end pt-2">
 <x-ui.button type="submit" variant="primary" size="sm">{{ __('en.email_password_reset_link') }}</x-ui.button>
 </div>
 </form>
</x-guest-layout>
