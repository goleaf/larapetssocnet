<section class="space-y-4" x-data="dangerZoneConfirm(@js((string) (($user ?? auth()->user())?->username ?? '')))" aria-label="Danger zone section">
 <header>
 <h2 class="shell-title text-lg text-rose">Danger Zone</h2>
 <p class="mt-1 text-sm shell-text-muted">
 Deleting your account permanently removes your profile, pets, posts, and social connections.
 </p>
 </header>

 <div class="ui-panel border-rose/40 bg-rose-light/30 p-4">
 <p class="text-sm font-semibold">Confirm account deletion</p>
	 <p class="mt-1 text-sm shell-text-muted">
	 Type
	 <span class="font-semibold" style="color: var(--ui-text);">{{ ($user ?? auth()->user())?->username ? (string) ($user ?? auth()->user())->username : 'your username' }}</span>
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
 <x-ui.input
 id="delete_confirm_username"
 name="username_confirmation"
 type="text"
 label="Username Confirmation"
 x-model="confirmation"
 autocomplete="off"
 autocapitalize="none"
 spellcheck="false"
 hint="Must match your username exactly."
 required
 />
 </div>

 <div>
 <x-ui.input
 id="delete_password"
 name="password"
 type="password"
 label="Password"
 autocomplete="current-password"
 required
 />
 </div>

 <p class="text-xs shell-text-muted" x-show="!canDelete" x-cloak>
 Username confirmation does not match yet.
 </p>

 <x-ui.button
 type="submit"
 variant="danger"
 :disabled="!canDelete || submitting"
 :aria-disabled="(!canDelete || submitting).toString()"
 >
 <span x-text="submitting ?'Deleting...':'Delete Account Permanently'"></span>
 </x-ui.button>
 </form>
 </div>
</section>
