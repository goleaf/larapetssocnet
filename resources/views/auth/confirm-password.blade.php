<x-guest-layout>
 <div class="mb-4 text-sm text-gray-600">
 {{ __('This is a secure area of the application. Please confirm your password before continuing.') }}
 </div>

 <form method="POST" action="{{ route('password.confirm') }}">
 @csrf

 <!-- Password -->
 <div>
 <x-ui.input id="password" type="password" name="password" :label="__('Password')" required autocomplete="current-password"/>
 </div>

 <div class="flex justify-end mt-4">
 <x-ui.button variant="primary">
 {{ __('Confirm') }}
 </x-ui.button>
 </div>
 </form>
</x-guest-layout>
