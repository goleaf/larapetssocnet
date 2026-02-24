<x-guest-layout>
    <form method="POST" action="{{ route('register') }}">
        @csrf

        <div class="space-y-4">
            <!-- Name -->
            <div>
                <x-ui.input id="name" type="text" name="name" label="Name" :value="old(' name')" required autofocus
                    autocomplete="name" />
            </div>

            <!-- Username -->
            <div
                x-data="{ val: @js(old('username', '')), status: null, message: '', checking: false, async check() { if (this.val.length < 3) { this.status = null; this.message = ''; return; } this.checking = true; const res = await fetch('{{ route('api.username.available') }}?username=' + encodeURIComponent(this.val)); const data = await res.json(); this.status = data.available ? 'ok' : 'taken'; this.message = data.message ?? ''; this.checking = false; } }">
                <div class="relative">
                    <x-ui.label for="username">Username (optional)</x-ui.label>
                    <div class="relative mt-1">
                        <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-fur">@</span>
                        <input id="username"
                            class="block w-full rounded-xl border border-whisker/30 bg-warm-white py-2 pl-8 pr-3 text-sm placeholder:text-fur focus:border-paw focus:outline-none focus:ring-1 focus:ring-paw transition-colors duration-200"
                            type="text" name="username" :value="old('username')" maxlength="30" autocomplete="username"
                            x-model="val" @input.debounce.400ms="check()"
                            x-bind:class="{ '!border-success !focus:ring-success': status === 'ok', '!border-danger !focus:ring-danger': status === 'taken' }" />
                    </div>
                    <div class="mt-1 flex items-center justify-between text-xs">
                        <span
                            class="text-fur">{{ __('3–30 chars. Letters, numbers, underscores. If empty, one will be generated.') }}</span>
                        <span x-show="checking" class="text-fur">Checking...</span>
                        <span x-show="status === 'ok'" class="text-success font-medium">✓ <span
                                x-text="message"></span></span>
                        <span x-show="status === 'taken'" class="text-danger font-medium">✗ <span
                                x-text="message"></span></span>
                    </div>
                </div>
                <x-input-error :messages="$errors->get(' username')" class="mt-2" />
            </div>

            <!-- Email Address -->
            <div>
                <x-ui.input id="email" type="email" name="email" label="Email" :value="old(' email')" required
                    autocomplete="username" />
            </div>

            <!-- Password -->
            <div>
                <x-ui.input id="password" type="password" name="password" label="Password" required
                    autocomplete="new-password" />
            </div>

            <!-- Confirm Password -->
            <div>
                <x-ui.input id="password_confirmation" type="password" name="password_confirmation"
                    label="Confirm Password" required autocomplete="new-password" />
            </div>

            <div class="flex flex-wrap items-center justify-end mt-6 gap-3 pt-6 border-t border-whisker/30">
                <a class="text-sm text-paw hover:underline focus:outline-none" href="{{ route('login') }}">
                    {{ __('Already registered?') }}
                </a>

                <x-ui.button type="submit" variant="primary">
                    {{ __('Register') }}
                </x-ui.button>
            </div>
        </div>
    </form>
</x-guest-layout>