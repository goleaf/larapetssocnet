<x-guest-layout>
 <x-auth-session-status class="mb-4" :status="session('status')"/>

 <form method="POST" action="{{ route('login') }}">
 @csrf

 <div class="space-y-4">
 <div>
 <x-ui.input id="email" type="text" name="email" label="Email or Username" :value="old('email')" required autofocus
 autocomplete="username"/>
 </div>

 <div>
 <x-ui.input id="password" type="password" name="password" label="Password" required
 autocomplete="current-password"/>
 </div>

 <div class="block mt-4">
 <label for="remember_me" class="inline-flex items-center group cursor-pointer">
 <input id="remember_me" type="checkbox"
 class="rounded border-whisker/50 text-paw shadow-sm focus:ring-paw" name="remember">
 <span
 class="ms-2 text-sm text-fur group-hover:text-bark transition-colors">{{ __('Remember me') }}</span>
 </label>
 </div>

 <div class="flex items-center justify-end mt-4 gap-4">
 @if (Route::has('password.request'))
 <a class="text-sm text-paw hover:underline focus:outline-none" href="{{ route('password.request') }}">
 {{ __('Forgot your password?') }}
 </a>
 @endif

 <x-ui.button type="submit" variant="primary">
 {{ __('Log in') }}
 </x-ui.button>
 </div>
 </div>
 </form>

 @if (($loginUsers ?? collect())->isNotEmpty())
 <section class="mt-5 rounded-xl border border-whisker/40 bg-cream/50 p-4">
 <div class="flex items-center justify-between gap-3">
 <p class="text-sm font-semibold text-bark">Quick Login Users</p>
 <code class="rounded-lg bg-warm-white px-2 py-1 text-xs font-semibold text-paw">password</code>
 </div>
 <p class="mt-1 text-xs text-fur">Use username (or email) and the shared password above.</p>

 <ul class="mt-3 max-h-64 space-y-2 overflow-y-auto pr-1">
 @forelse ($loginUsers as $loginUser)
 <li class="rounded-lg border border-whisker/30 bg-warm-white/85 px-3 py-2">
 <p class="truncate text-sm font-semibold text-bark">
 {{ $loginUser->username ? '@'.$loginUser->username : $loginUser->email }}
 </p>
 <p class="truncate text-xs text-fur">{{ $loginUser->email }}</p>
 </li>
 @empty
 <li class="rounded-lg border border-whisker/30 bg-warm-white/85 px-3 py-2 text-xs text-fur">
 No users found.
 </li>
 @endforelse
 </ul>
 </section>
 @endif
</x-guest-layout>
