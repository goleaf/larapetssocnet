@php
 $deletingUser = $user ?? auth()->user();
 $requiredUsername = (string) ($deletingUser?->username ??'');
@endphp

<section class="space-y-4" x-data="dangerZoneConfirm(@js($requiredUsername))" aria-label="Danger zone section">
 <header>
 <h2 class="shell-title text-lg" style="color: color-mix(in srgb, var(--ui-danger) 70%, var(--ui-text) 30%);">Danger Zone</h2>
 <p class="mt-1 text-sm shell-text-muted">
 Deleting your account permanently removes your profile, pets, posts, and social connections.
 </p>
 </header>

 <div class="rounded-2xl border p-4" style="border-color: color-mix(in srgb, var(--ui-danger) 38%, var(--ui-border) 62%); background: color-mix(in srgb, var(--ui-danger) 8%, var(--ui-surface) 92%);">
 <p class="text-sm font-semibold">Confirm account deletion</p>
 <p class="mt-1 text-sm shell-text-muted">
 Type
 <span class="font-semibold" style="color: var(--ui-text);">{{ $requiredUsername !==''? $requiredUsername :'your username'}}</span>
 and enter your password.
 </p>

 <form
 method="post"
 action="{{ route('settings.account.destroy') }}"
 class="mt-4 space-y-4"
 @submit="submitting = true"
 >
 @csrf
 @method('delete')

 <div>
 <label for="delete_confirm_username" class="mb-1 block text-sm font-semibold">Username Confirmation</label>
 <input
 id="delete_confirm_username"
 type="text"
 class="form-input"
 x-model="confirmation"
 autocomplete="off"
 autocapitalize="none"
 spellcheck="false"
 aria-describedby="delete-confirm-help"
 required
 />
 <p id="delete-confirm-help" class="mt-1 text-xs shell-text-muted">Must match your username exactly.</p>
 </div>

 <div>
 <label for="delete_password" class="mb-1 block text-sm font-semibold">Password</label>
 <input
 id="delete_password"
 name="password"
 type="password"
 class="form-input"
 autocomplete="current-password"
 required
 aria-label="Password confirmation"
 />
 <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-2"/>
 </div>

 <p class="text-xs shell-text-muted" x-show="!canDelete" x-cloak>
 Username confirmation does not match yet.
 </p>

 <button
 type="submit"
 class="btn-base btn-danger"
 :disabled="!canDelete || submitting"
 :aria-disabled="(!canDelete || submitting).toString()"
 >
 <span x-text="submitting ?'Deleting...':'Delete Account Permanently'"></span>
 </button>
 </form>
 </div>
</section>
