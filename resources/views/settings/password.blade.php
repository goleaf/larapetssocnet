<x-settings-layout>
 <div class="space-y-6">
 <div>
 <h3 class="text-lg font-semibold text-bark">Password</h3>
 <p class="mt-1 text-sm text-fur">Ensure your account is using a long, random password to stay secure.
 </p>
 </div>

 <form action="{{ route('settings.password.update') }}" method="POST" class="space-y-6">
 @csrf
 @method('PUT')

 <div class="grid grid-cols-1 gap-y-6 sm:grid-cols-6 sm:gap-x-6">
 <!-- Current Password -->
 <div class="sm:col-span-4">
 <x-ui.input id="current_password" name="current_password" type="password" label="Current Password"
 autocomplete="current-password" required/>
 </div>

 <!-- New Password -->
 <div class="sm:col-span-4">
 <x-ui.input id="password" name="password" type="password" label="New Password" autocomplete="new-password" required/>
 </div>

 <!-- Confirm Password -->
 <div class="sm:col-span-4">
 <x-ui.input id="password_confirmation" name="password_confirmation" type="password" label="Confirm New Password"
 autocomplete="new-password" required/>
 </div>
 </div>

 <div class="flex justify-start border-t border-whisker/30 pt-5">
 <x-ui.button variant="primary">Save Password</x-ui.button>
 </div>
 </form>

 <div class="mt-10 border-t border-whisker/30 pt-6">
 <h3 class="text-lg font-semibold text-bark">Security Information</h3>
 <dl class="mt-4 divide-y divide-whisker/30">
 <div class="py-4 sm:grid sm:grid-cols-3 sm:gap-4">
 <dt class="text-sm font-medium text-fur">Last password change</dt>
 <dd class="mt-1 text-sm text-bark sm:col-span-2 sm:mt-0">
 {{ auth()->user()->password_changed_at ? auth()->user()->password_changed_at->diffForHumans() :'Never'}}
 </dd>
 </div>
 <div class="py-4 sm:grid sm:grid-cols-3 sm:gap-4">
 <dt class="text-sm font-medium text-fur">Active sessions</dt>
 <dd class="mt-1 text-sm text-bark sm:col-span-2 sm:mt-0">
 Logged in from this browser. You can log out of all other active sessions via the main logout
 action.
 </dd>
 </div>
 </dl>
 </div>
 </div>
</x-settings-layout>
