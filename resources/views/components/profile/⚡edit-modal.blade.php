<?php

use App\Actions\Users\UpdateProfileAction;
use App\Models\Identity\User;
use App\Services\Auth\AuthAuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public int $userId;

    public ?string $focusTarget = null;

    public string $name = '';

    public ?string $display_name = null;

    public ?string $bio = null;

    public ?string $headline = null;

    public ?string $location = null;

    public ?string $website = null;

    public ?string $birth_date = null;

    public mixed $avatar = null;

    public mixed $cover = null;

    public bool $remove_avatar = false;

    public bool $remove_cover = false;

    public function mount(int $userId, ?string $focusTarget = null): void
    {
        $this->userId = $userId;
        $this->focusTarget = $this->sanitizeFocusTarget($focusTarget);

        $user = $this->profileUser();
        $viewer = $this->viewer();

        abort_unless($viewer instanceof User && $viewer->is($user), 403);

        Gate::forUser($viewer)->authorize('update', $user);

        $this->name = (string) $user->name;
        $this->display_name = $user->display_name;
        $this->bio = $user->bio;
        $this->headline = $user->headline;
        $this->location = $user->location;
        $this->website = $user->website;
        $this->birth_date = $user->birth_date?->format('Y-m-d');
    }

    public function save(UpdateProfileAction $updateProfile, AuthAuditLogger $auditLogger): void
    {
        $user = $this->profileUser();
        $viewer = $this->viewer();

        abort_unless($viewer instanceof User && $viewer->is($user), 403);

        Gate::forUser($viewer)->authorize('update', $user);

        $this->normalizeForValidation();

        $validated = $this->validate();

        $updateProfile->handle($user, [
            'name' => $validated['name'],
            'display_name' => $validated['display_name'] ?? null,
            'bio' => $validated['bio'] ?? null,
            'headline' => $validated['headline'] ?? null,
            'location' => $validated['location'] ?? null,
            'website' => $validated['website'] ?? null,
            'birth_date' => $validated['birth_date'] ?? null,
            'avatar' => $this->avatar instanceof UploadedFile ? $this->avatar : null,
            'cover' => $this->cover instanceof UploadedFile ? $this->cover : null,
            'remove_avatar' => (bool) ($validated['remove_avatar'] ?? false),
            'remove_cover' => (bool) ($validated['remove_cover'] ?? false),
        ]);

        $changedFields = $this->changedFields($validated);

        $auditLogger->record($viewer, 'profile_updated', request(), [
            'changed_fields' => $changedFields,
            'changed_field_count' => count($changedFields),
        ]);

        $this->reset(['avatar', 'cover', 'remove_avatar', 'remove_cover']);
        $this->resetValidation();

        $this->js("document.body.classList.remove('overflow-hidden')");
        $this->dispatch('profile-edit-saved');
    }

    public function close(): void
    {
        $this->js("document.body.classList.remove('overflow-hidden')");
        $this->dispatch('profile-edit-closed');
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'display_name' => ['nullable', 'string', 'max:120'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'headline' => ['nullable', 'string', 'max:120'],
            'location' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
            'remove_avatar' => ['boolean'],
            'remove_cover' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'avatar.image' => 'Avatar must be an image file.',
            'avatar.mimes' => 'Avatar must be a JPG, PNG, or WEBP image.',
            'avatar.max' => 'Avatar must be smaller than 10MB.',
            'cover.image' => 'Cover must be an image file.',
            'cover.mimes' => 'Cover must be a JPG, PNG, WEBP, or GIF image.',
            'cover.max' => 'Cover must be smaller than 5MB.',
        ];
    }

    private function profileUser(): User
    {
        return User::query()
            ->with('media')
            ->whereKey($this->userId)
            ->firstOrFail();
    }

    private function viewer(): ?User
    {
        $viewer = auth()->user();

        return $viewer instanceof User ? $viewer : null;
    }

    private function normalizeForValidation(): void
    {
        $this->name = trim($this->name);
        $this->display_name = $this->nullableString($this->display_name);
        $this->bio = $this->nullableString($this->bio);
        $this->headline = $this->nullableString($this->headline);
        $this->location = $this->nullableString($this->location);
        $this->birth_date = $this->nullableString($this->birth_date);

        $website = $this->nullableString($this->website);

        if ($website !== null && ! preg_match('/^https?:\/\//i', $website)) {
            $website = 'https://'.$website;
        }

        $this->website = $website;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function sanitizeFocusTarget(?string $target): ?string
    {
        $target = trim((string) $target);

        $allowedTargets = [
            'profile_modal_avatar_field',
            'profile_modal_cover_field',
            'profile_modal_name',
            'profile_modal_display_name',
            'profile_modal_bio',
            'profile_modal_headline',
            'profile_modal_location',
            'profile_modal_website',
            'profile_modal_birth_date',
            'profile_modal_pets',
            'profile_modal_following',
        ];

        return in_array($target, $allowedTargets, true) ? $target : null;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return list<string>
     */
    private function changedFields(array $validated): array
    {
        $fields = [
            'name',
            'display_name',
            'bio',
            'headline',
            'location',
            'website',
            'birth_date',
        ];

        if (($validated['avatar'] ?? null) instanceof UploadedFile) {
            $fields[] = 'avatar';
        }

        if (($validated['cover'] ?? null) instanceof UploadedFile) {
            $fields[] = 'cover';
        }

        if ((bool) ($validated['remove_avatar'] ?? false)) {
            $fields[] = 'remove_avatar';
        }

        if ((bool) ($validated['remove_cover'] ?? false)) {
            $fields[] = 'remove_cover';
        }

        return array_values(array_unique($fields));
    }
};
?>

@php
 $user = $this->profileUser();
 $displayName = $user->display_name ?: $user->name;
 $coverUrl = $user->coverImageUrl();
@endphp

<div
 data-ui="profile-edit-modal"
 class="fixed inset-0 z-50"
 x-data="{
 focusTarget: @js($focusTarget),
 focusInitial() {
 const target = this.focusTarget ? document.getElementById(this.focusTarget) : null;
 const focusableTarget = target?.matches('input, textarea, select, button, a')
 ? target
 : target?.querySelector('input, textarea, select, button, a');

 if (target) {
 target.scrollIntoView({ behavior: 'smooth', block: 'center' });
 }

 (focusableTarget || this.$refs.closeButton)?.focus({ preventScroll: Boolean(focusableTarget) });
 },
 trapFocus(event) {
 const focusable = Array.from(this.$el.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), textarea:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex=\'-1\'])'))
 .filter((element) => element.offsetParent !== null);

 if (focusable.length === 0) {
 return;
 }

 const first = focusable[0];
 const last = focusable[focusable.length - 1];

 if (event.shiftKey && document.activeElement === first) {
 event.preventDefault();
 last.focus();
 return;
 }

 if (! event.shiftKey && document.activeElement === last) {
 event.preventDefault();
 first.focus();
 }
 },
 close() {
 document.body.classList.remove('overflow-hidden');
 $wire.close();
 },
 }"
 x-init="document.body.classList.add('overflow-hidden'); $nextTick(() => focusInitial())"
 @keydown.tab="trapFocus($event)"
 @keydown.escape.window="close()"
>
 <button
 type="button"
 class="absolute inset-0 h-full w-full bg-bark/50"
 aria-label="Close edit profile modal"
 @click="close()"
 ></button>

 <div class="relative flex min-h-screen items-stretch justify-center sm:items-center sm:p-6">
 <section
 class="flex h-screen w-full flex-col overflow-hidden bg-warm-white shadow-card sm:h-auto sm:max-h-[calc(100vh-3rem)] sm:max-w-3xl sm:rounded-[var(--radius-card)] sm:border sm:border-whisker/40"
 role="dialog"
 aria-modal="true"
 aria-labelledby="profile-edit-modal-title"
 aria-describedby="profile-edit-modal-description"
 @click.stop
 >
 <header class="flex shrink-0 items-start justify-between gap-4 border-b border-whisker/40 px-4 py-4 sm:px-6">
 <div class="min-w-0">
 <p class="text-xs font-semibold uppercase tracking-wide text-fur">Profile</p>
 <h2 id="profile-edit-modal-title" class="mt-1 text-xl font-bold font-display text-bark">Edit Profile</h2>
 <p id="profile-edit-modal-description" class="mt-1 text-sm leading-6 text-fur">Update the public details people see while keeping your profile visible behind this editor.</p>
 </div>
 <button
 x-ref="closeButton"
 type="button"
 class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-[var(--radius-control)] text-fur transition-colors hover:bg-cream hover:text-bark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw"
 wire:click="close"
 aria-label="Close edit profile modal"
 >
 <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
 <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
 </svg>
 </button>
 </header>

 <form wire:submit.prevent="save" enctype="multipart/form-data" class="flex min-h-0 flex-1 flex-col" data-ui="profile-edit-modal-form">
 <div class="min-h-0 flex-1 overflow-y-auto px-4 py-5 sm:px-6">
 <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
 <div id="profile_modal_avatar_field" class="space-y-3 rounded-[var(--radius-card)] border border-whisker/40 bg-cream/30 p-4 sm:col-span-1" data-ui="profile-modal-avatar-field">
 <x-ui.file-upload
 id="profile_modal_avatar"
 name="avatar"
 label="Avatar"
 accept="image/jpeg,image/png,image/webp"
 maxSize="10MB"
 preview
 help="JPG, PNG, or WEBP. Square image recommended."
 :error="$errors->first('avatar')"
 wire:model="avatar"
 />
 <div class="flex items-center gap-3">
 <x-ui.avatar :src="$user->avatar_url" :name="$user->name" size="md"/>
 <span class="text-xs leading-5 text-fur">Shown beside your posts and in profile lists.</span>
 </div>
 @if ($user->avatar_url)
 <x-ui.checkbox id="profile_modal_remove_avatar" name="remove_avatar" label="Remove current avatar" wire:model="remove_avatar"/>
 @endif
 </div>

 <div id="profile_modal_cover_field" class="space-y-3 rounded-[var(--radius-card)] border border-whisker/40 bg-cream/30 p-4 sm:col-span-1" data-ui="profile-modal-cover-field">
 <x-ui.file-upload
 id="profile_modal_cover"
 name="cover"
 label="Cover Photo"
 accept="image/jpeg,image/png,image/webp,image/gif"
 maxSize="5MB"
 preview
 help="JPG, PNG, WEBP, or GIF. Recommended 1600x480."
 :error="$errors->first('cover')"
 wire:model="cover"
 />
 @if ($coverUrl)
 <img src="{{ $coverUrl }}" alt="{{ $user->name }} cover preview" class="h-20 w-full rounded-[var(--radius-soft)] object-cover">
 <x-ui.checkbox id="profile_modal_remove_cover" name="remove_cover" label="Remove current cover photo" wire:model="remove_cover"/>
 @endif
 </div>

 <x-ui.input id="profile_modal_name" name="name" label="Name" :value="$name" required autocomplete="name" :error="$errors->first('name')" wire:model.live.blur="name"/>
 <x-ui.input id="profile_modal_display_name" name="display_name" label="Display name" :value="$display_name" autocomplete="nickname" :error="$errors->first('display_name')" wire:model.live.blur="display_name"/>
 <div class="sm:col-span-2">
 <x-ui.textarea id="profile_modal_bio" name="bio" rows="4" label="Bio" :value="$bio" maxlength="1000" hint="Brief description for your profile." :error="$errors->first('bio')" wire:model.live.blur="bio"/>
 </div>
 <div class="sm:col-span-2">
 <x-ui.input id="profile_modal_headline" name="headline" label="Headline" :value="$headline" hint="Short status or tagline shown near your name." :error="$errors->first('headline')" wire:model.live.blur="headline"/>
 </div>
 <x-ui.input id="profile_modal_location" name="location" label="Location" :value="$location" :error="$errors->first('location')" wire:model.live.blur="location"/>
 <x-ui.input id="profile_modal_website" name="website" type="url" label="Website" :value="$website" :error="$errors->first('website')" wire:model.live.blur="website"/>
 <x-ui.input id="profile_modal_birth_date" name="birth_date" type="date" label="Birth Date" :value="$birth_date" :error="$errors->first('birth_date')" wire:model.live.blur="birth_date"/>

 <div id="profile_modal_pets" class="flex flex-col gap-2 rounded-[var(--radius-card)] border border-whisker/40 bg-cream/30 p-4" data-ui="profile-modal-pets-field">
 <p class="text-sm font-semibold text-bark">Pets</p>
 <p class="text-xs leading-5 text-fur">Add a pet profile from the Pets tab so visitors can meet your companion without leaving your profile.</p>
 </div>

 <div id="profile_modal_following" class="flex flex-col gap-2 rounded-[var(--radius-card)] border border-whisker/40 bg-cream/30 p-4" data-ui="profile-modal-following-field">
 <p class="text-sm font-semibold text-bark">Following</p>
 <p class="text-xs leading-5 text-fur">Follow a few members from profile suggestions to personalize your community graph.</p>
 </div>
 </div>
 </div>

 <footer class="flex shrink-0 flex-col gap-2 border-t border-whisker/30 bg-cream/35 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
 <p class="text-sm font-medium text-fur" role="status" aria-live="polite">
 @if ($errors->any())
 Fix the highlighted fields before saving.
 @else
 Editing {{ $displayName }}.
 @endif
 </p>
 <div class="grid grid-cols-2 gap-2 sm:flex sm:items-center">
 <x-ui.button type="button" variant="outline" size="sm" class="min-h-11" wire:click="close">Cancel</x-ui.button>
 <x-ui.button type="submit" variant="primary" size="sm" class="min-h-11" wire:loading.attr="disabled" wire:target="save,avatar,cover">
 <span wire:loading.remove wire:target="save">Save Profile</span>
 <span wire:loading wire:target="save">Saving...</span>
 </x-ui.button>
 </div>
 </footer>
 </form>
 </section>
 </div>
</div>
