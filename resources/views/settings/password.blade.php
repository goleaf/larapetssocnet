<x-settings-layout>
 <div class="space-y-6">
 <div>
 <h3 class="text-lg font-medium leading-6 text-gray-900">Password</h3>
 <p class="mt-1 text-sm text-gray-500">Ensure your account is using a long, random password to stay secure.
 </p>
 </div>

 <form action="{{ route('settings.password.update') }}"method="POST"class="space-y-6">
 @csrf
 @method('PUT')

 <div class="grid grid-cols-1 gap-y-6 sm:grid-cols-6 sm:gap-x-6">
 <!-- Current Password -->
 <div class="sm:col-span-4">
 <x-input-label for="current_password"value="Current Password"/>
 <x-text-input id="current_password"name="current_password"type="password"
 class="mt-1 block w-full"autocomplete="current-password"required />
 <x-input-error class="mt-2":messages="$errors->get('current_password')"/>
 </div>

 <!-- New Password -->
 <div class="sm:col-span-4">
 <x-input-label for="password"value="New Password"/>
 <x-text-input id="password"name="password"type="password"class="mt-1 block w-full"
 autocomplete="new-password"required />
 <x-input-error class="mt-2":messages="$errors->get('password')"/>
 </div>

 <!-- Confirm Password -->
 <div class="sm:col-span-4">
 <x-input-label for="password_confirmation"value="Confirm New Password"/>
 <x-text-input id="password_confirmation"name="password_confirmation"type="password"
 class="mt-1 block w-full"autocomplete="new-password"required />
 <x-input-error class="mt-2":messages="$errors->get('password_confirmation')"/>
 </div>
 </div>

 <div class="flex justify-start border-t border-gray-200 pt-5">
 <x-primary-button>Save Password</x-primary-button>
 </div>
 </form>

 <div class="mt-10 border-t border-gray-200 pt-6">
 <h3 class="text-lg font-medium leading-6 text-gray-900">Security Information</h3>
 <dl class="mt-4 divide-y divide-gray-200">
 <div class="py-4 sm:grid sm:grid-cols-3 sm:gap-4">
 <dt class="text-sm font-medium text-gray-500">Last password change</dt>
 <dd class="mt-1 text-sm text-gray-900 sm:col-span-2 sm:mt-0">
 {{ auth()->user()->password_changed_at ? auth()->user()->password_changed_at->diffForHumans() :'Never'}}
 </dd>
 </div>
 <div class="py-4 sm:grid sm:grid-cols-3 sm:gap-4">
 <dt class="text-sm font-medium text-gray-500">Active sessions</dt>
 <dd class="mt-1 text-sm text-gray-900 sm:col-span-2 sm:mt-0">
 Logged in from this browser. You can log out of all other active sessions via the main logout
 action.
 </dd>
 </div>
 </dl>
 </div>
 </div>
</x-settings-layout>