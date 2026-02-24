<x-guest-layout>
 <x-ui.alert type="info" class="mb-4">
 {{ __('Forgot your password? No problem. Just let us know your email address and we will email you a password reset link that will allow you to choose a new one.') }}
 </x-ui.alert>

 @if (session('status'))
 <x-ui.alert type="success" class="mb-4">{{ session('status') }}</x-ui.alert>
 @endif

 <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
 @csrf

 <x-ui.input
 name="email"
 label="{{ __('Email') }}"
 type="email"
 :value="old(' email')"
 required
 autofocus
 />

 <div class="flex items-center justify-end pt-2">
 <x-ui.button type="submit" variant="primary" size="sm">{{ __('Email Password Reset Link') }}</x-ui.button>
 </div>
 </form>
</x-guest-layout>
