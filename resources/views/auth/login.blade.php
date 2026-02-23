<x-guest-layout>
 @if (session('status'))
 <x-ui.alert type="success"class="mb-4">{{ session('status') }}</x-ui.alert>
 @endif

 <form method="POST"action="{{ route('login') }}"class="space-y-4">
 @csrf

 <x-ui.input
 name="email"
 label="{{ __('Email') }}"
 type="email"
 :value="old('email')"
 required
 autofocus
 autocomplete="username"
 />

 <x-ui.input
 name="password"
 label="{{ __('Password') }}"
 type="password"
 required
 autocomplete="current-password"
 />

 <x-ui.checkbox name="remember"label="{{ __('Remember me') }}"value="1":checked="old('remember')"/>

 <div class="flex items-center justify-between gap-3 pt-2">
 @if (Route::has('password.request'))
 <x-ui.button href="{{ route('password.request') }}"variant="ghost"size="sm">
 {{ __('Forgot your password?') }}
 </x-ui.button>
 @else
 <span></span>
 @endif

 <x-ui.button type="submit"variant="primary"size="sm">{{ __('Log in') }}</x-ui.button>
 </div>
 </form>
</x-guest-layout>
