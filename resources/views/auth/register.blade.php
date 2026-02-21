<x-guest-layout>
    <form method="POST" action="{{ route('register') }}">
        @csrf

        <!-- Name -->
        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <!-- Username -->
        <div class="mt-4">
            <x-input-label for="username" :value="__('Username (optional)')" />
            <div
                class="relative mt-1"
                x-data="{ val: @js(old('username', '')), status: null, message: '', checking: false, async check() { if (this.val.length < 3) { this.status = null; this.message = ''; return; } this.checking = true; const res = await fetch('{{ route('api.username.available') }}?username=' + encodeURIComponent(this.val)); const data = await res.json(); this.status = data.available ? 'ok' : 'taken'; this.message = data.message ?? ''; this.checking = false; } }"
            >
                <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-400">@</span>
                <x-text-input
                    id="username"
                    class="block w-full pl-7"
                    type="text"
                    name="username"
                    :value="old('username')"
                    maxlength="30"
                    autocomplete="username"
                    x-model="val"
                    @input.debounce.400ms="check()"
                    x-bind:class="{ 'border-emerald-500 focus:ring-emerald-500': status === 'ok', 'border-red-500 focus:ring-red-500': status === 'taken' }"
                />
                <div class="mt-1 h-5 text-sm">
                    <span x-show="checking" class="text-gray-400">Checking...</span>
                    <span x-show="status === 'ok'" class="text-emerald-600">✓ <span x-text="message"></span></span>
                    <span x-show="status === 'taken'" class="text-red-600">✗ <span x-text="message"></span></span>
                </div>
            </div>
            <p class="mt-1 text-xs text-gray-500">{{ __('3–30 chars. Letters, numbers, underscores. If empty, one will be generated.') }}</p>
            <x-input-error :messages="$errors->get('username')" class="mt-2" />
        </div>

        <!-- Email Address -->
        <div class="mt-4">
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />

            <x-text-input id="password_confirmation" class="block mt-1 w-full"
                            type="password"
                            name="password_confirmation" required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <a class="underline text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800" href="{{ route('login') }}">
                {{ __('Already registered?') }}
            </a>

            <x-primary-button class="ms-4">
                {{ __('Register') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
