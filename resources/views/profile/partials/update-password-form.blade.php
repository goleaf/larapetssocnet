<section aria-label="Update password section">
 <header>
 <h2 class="shell-title text-lg">Update Password</h2>
 <p class="mt-1 text-sm shell-text-muted">Use a long, unique password to protect your account.</p>
 </header>

 <form method="post" action="{{ route('password.update') }}" class="mt-6 space-y-5">
 @csrf
 @method('put')

 <div>
 <x-ui.input id="update_password_current_password" name="current_password" type="password" label="Current Password" autocomplete="current-password"/>
 </div>

 <div>
 <x-ui.input id="update_password_password" name="password" type="password" label="New Password" autocomplete="new-password"/>
 </div>

 <div>
 <x-ui.input id="update_password_password_confirmation" name="password_confirmation" type="password" label="Confirm Password" autocomplete="new-password"/>
 </div>

 <div class="flex items-center gap-4">
 <x-ui.button type="submit" variant="primary">Save Password</x-ui.button>

 @if (session('status') ==='password-updated')
 <p
 x-data="{ show: true }"
 x-show="show"
 x-transition
 x-init="setTimeout(() => show = false, 2200)"
 class="text-sm shell-text-muted"
 >Saved.</p>
 @endif
 </div>
 </form>
</section>
