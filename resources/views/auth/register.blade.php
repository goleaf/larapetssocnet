<x-guest-layout>
 <div class="mb-6 space-y-2" data-ui="auth-form-header">
 <p class="chip min-h-8">Create account</p>
 <h1 class="shell-title text-2xl">Start your pet profile network</h1>
 <p class="text-sm leading-6 shell-text-muted">Create your account, then add pets, join groups, and browse public activity.</p>
 </div>

 <form method="POST" action="{{ route('register') }}" data-ui="register-form">
 @csrf

 <div class="space-y-4">
 <div class="grid gap-4 sm:grid-cols-2">
 <div class="sm:col-span-2">
 <x-ui.input id="name" type="text" name="name" label="Name" :value="old('name')" required autofocus
 autocomplete="name"/>
 </div>

 <div
 class="sm:col-span-2"
 x-data="{ val: @js(old('username','')), status: null, message:'', checking: false, async check() { if (this.val.length < 3) { this.status = null; this.message =''; return; } this.checking = true; const res = await fetch('{{ route('api.username.available') }}?username='+ encodeURIComponent(this.val)); const data = await res.json(); this.status = data.available ?'ok':'taken'; this.message = data.message ??''; this.checking = false; } }">
 <div class="relative">
 <x-ui.input
 id="username"
 type="text"
 name="username"
 label="Username (optional)"
 prefix="@"
 :value="old('username')"
 maxlength="30"
 autocomplete="username"
 x-model="val"
 @input.debounce.400ms="check()"
 x-bind:class="{'!border-success !focus:ring-success': status ==='ok','!border-danger !focus:ring-danger': status ==='taken'}"
 />
 <div class="mt-1 flex items-center justify-between text-xs">
 <span class="text-fur">{{ __('3–30 chars. Letters, numbers, underscores. If empty, one will be generated.') }}</span>
 <span x-show="checking" class="text-fur">Checking...</span>
 <span x-show="status ==='ok'" class="text-success font-medium">✓ <span x-text="message"></span></span>
 <span x-show="status ==='taken'" class="text-danger font-medium">✗ <span x-text="message"></span></span>
 </div>
 </div>
 </div>

 <div class="sm:col-span-2">
 <x-ui.input id="email" type="email" name="email" label="Email" :value="old('email')" required
 autocomplete="username"/>
 </div>

 <div>
 <x-ui.input id="password" type="password" name="password" label="Password" required
 autocomplete="new-password"/>
 </div>

 <div>
 <x-ui.input id="password_confirmation" type="password" name="password_confirmation"
 label="Confirm Password" required autocomplete="new-password"/>
 </div>
 </div>

 <div class="flex flex-col-reverse gap-3 border-t border-whisker/30 pt-5 sm:flex-row sm:items-center sm:justify-between">
 <a class="inline-flex min-h-11 items-center text-sm font-semibold text-paw hover:text-paw-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw" href="{{ route('login') }}">
 {{ __('Already registered?') }}
 </a>

 <x-ui.button type="submit" variant="primary" class="min-h-11 sm:min-w-32">
 {{ __('Register') }}
 </x-ui.button>
 </div>
 </div>
 </form>
</x-guest-layout>
