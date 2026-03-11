<x-guest-layout>
    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')"  />

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div class="space-y-4">
            <div>
                <x-ui.input id="email" type="email" name="email" label="Email" :value="old('email')" required autofocus
                    autocomplete="username"  />
            </div>

            <!-- Password -->
            <div>
                <x-ui.input id="password" type="password" name="password" label="Password" required
                    autocomplete="current-password"  />
            </div>

            <!-- Remember Me -->
            <div class="block mt-4">
                <label for="remember_me" class="inline-flex items-center group cursor-pointer">
                    <input id="remember_me" type="checkbox"
                        class="rounded border-whisker/50 text-paw shadow-sm focus:ring-paw" name="remember">
                    <span
                        class="ms-2 text-sm text-fur group-hover:text-bark transition-colors">{{ __('en.remember_me') }}</span>
                </label>
            </div>

            <div class="flex items-center justify-end mt-4 gap-4">
                @if (Route::has('password.request'))
                    <a class="text-sm text-paw hover:underline focus:outline-none" href="{{ route('password.request') }}">
                        {{ __('en.forgot_your_password') }}
                    </a>
                @endif

                <x-ui.button type="submit" variant="primary">
                    {{ __('en.log_in') }}
                </x-ui.button>
            </div>
        </div>
    </form>
</x-guest-layout>