<x-guest-layout>
 <div class="mb-4 text-sm text-gray-400">
 {{ __('en.this_is_a_secure_area_of_the_application_please_confirm_your_password_before_con') }}
 </div>

 <form method="POST" action="{{ route('password.confirm') }}">
 @csrf

 <!-- Password -->
 <div>
 <x-input-label for="password" :value="__('en.password')" />

 <x-text-input id="password" class="block mt-1 w-full"
 type="password"
 name="password"
 required autocomplete="current-password" />

 <x-input-error :messages="$errors->get('password')" class="mt-2" />
 </div>

 <div class="flex justify-end mt-4">
 <x-primary-button>
 {{ __('en.confirm') }}
 </x-primary-button>
 </div>
 </form>
</x-guest-layout>
