<section aria-label="Update password section">
 <header>
 <h2 class="shell-title text-lg">Update Password</h2>
 <p class="mt-1 text-sm shell-text-muted">Use a long, unique password to protect your account.</p>
 </header>

 <form method="post"action="{{ route('password.update') }}"class="mt-6 space-y-5">
 @csrf
 @method('put')

 <div>
 <label for="update_password_current_password"class="mb-1 block text-sm font-semibold">Current Password</label>
 <input id="update_password_current_password"name="current_password"type="password"class="form-input"autocomplete="current-password"aria-label="Current password"/>
 <x-input-error :messages="$errors->updatePassword->get('current_password')"class="mt-2"/>
 </div>

 <div>
 <label for="update_password_password"class="mb-1 block text-sm font-semibold">New Password</label>
 <input id="update_password_password"name="password"type="password"class="form-input"autocomplete="new-password"aria-label="New password"/>
 <x-input-error :messages="$errors->updatePassword->get('password')"class="mt-2"/>
 </div>

 <div>
 <label for="update_password_password_confirmation"class="mb-1 block text-sm font-semibold">Confirm Password</label>
 <input id="update_password_password_confirmation"name="password_confirmation"type="password"class="form-input"autocomplete="new-password"aria-label="Confirm new password"/>
 <x-input-error :messages="$errors->updatePassword->get('password_confirmation')"class="mt-2"/>
 </div>

 <div class="flex items-center gap-4">
 <button type="submit"class="btn-base btn-primary">Save Password</button>

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
