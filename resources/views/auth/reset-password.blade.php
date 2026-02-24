<x-guest-layout>
 <form method="POST" action="{{ route('password.store') }}" class="space-y-4">
 @csrf

 <input type="hidden" name="token" value="{{ $request->route('token') }}">

 <x-ui.input
 name="email"
 label="{{ __('Email') }}"
 type="email"
 :value="old(' email', $request->email)"
 required
 autofocus
 autocomplete="username"
 />

 <x-ui.input
 name="password"
 label="{{ __('Password') }}"
 type="password"
 required
 autocomplete="new-password"
 />

 <x-ui.input
 name="password_confirmation"
 label="{{ __('Confirm Password') }}"
 type="password"
 required
 autocomplete="new-password"
 />

 <div class="flex items-center justify-end pt-2">
 <x-ui.button type="submit" variant="primary" size="sm">{{ __('Reset Password') }}</x-ui.button>
 </div>
 </form>
</x-guest-layout>
