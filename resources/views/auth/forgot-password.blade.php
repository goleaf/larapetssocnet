<x-guest-layout>
 <div class="mb-6 space-y-2" data-ui="auth-form-header">
 <p class="chip min-h-8">Password help</p>
 <h1 class="shell-title text-2xl">Reset access to your account</h1>
 <p class="text-sm leading-6 shell-text-muted">
 {{ __('Enter your email and we will send a secure reset link.') }}
 </p>
 </div>

 @if (session('status'))
 <x-ui.alert type="success" class="mb-4">{{ session('status') }}</x-ui.alert>
 @endif

 <form method="POST" action="{{ route('password.email') }}" class="space-y-4" data-ui="password-email-form">
 @csrf

 <x-ui.input
 name="email"
 label="{{ __('Email') }}"
 type="email"
 :value="old('email')"
 required
 autofocus
 />

 <div class="flex flex-col-reverse gap-3 border-t border-whisker/30 pt-5 sm:flex-row sm:items-center sm:justify-between">
 <a class="inline-flex min-h-11 items-center text-sm font-semibold text-paw hover:text-paw-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw" href="{{ route('login') }}">
 {{ __('Back to login') }}
 </a>

 <x-ui.button type="submit" variant="primary" class="min-h-11 sm:min-w-44">
 {{ __('Email reset link') }}
 </x-ui.button>
 </div>
 </form>
</x-guest-layout>
