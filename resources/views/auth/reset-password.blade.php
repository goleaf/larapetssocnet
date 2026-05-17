<x-guest-layout>
 <div class="mb-6 space-y-2" data-ui="auth-form-header">
 <p class="chip min-h-8">Choose password</p>
 <h1 class="shell-title text-2xl">Create a new password</h1>
 <p class="text-sm leading-6 shell-text-muted">
 {{ __('Use a fresh password to keep your pet community account protected.') }}
 </p>
 </div>

 <form method="POST" action="{{ route('password.store') }}" class="space-y-4" data-ui="password-reset-form">
 @csrf

 <input type="hidden" name="token" value="{{ $request->route('token') }}">

 <x-ui.input
 name="email"
 label="{{ __('Email') }}"
 type="email"
 :value="old('email', $request->email)"
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

 <div class="flex flex-col-reverse gap-3 border-t border-whisker/30 pt-5 sm:flex-row sm:items-center sm:justify-between">
 <a class="inline-flex min-h-11 items-center text-sm font-semibold text-paw hover:text-paw-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw" href="{{ route('login') }}">
 {{ __('Back to login') }}
 </a>

 <x-ui.button type="submit" variant="primary" class="min-h-11 sm:min-w-36">
 {{ __('Reset password') }}
 </x-ui.button>
 </div>
 </form>
</x-guest-layout>
