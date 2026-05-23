<?php

use App\Actions\Users\UpdateProfileAction;
use App\Models\Identity\User;
use App\Services\Auth\AuthAuditLogger;
use App\Services\SettingsService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
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

    public ?string $pronouns = null;

    public ?string $location = null;

    public ?string $website = null;

    public ?string $birth_date = null;

    public ?string $gender = null;

    /**
     * @var array{x?: string|null, instagram?: string|null, tiktok?: string|null, youtube?: string|null}
     */
    public array $social_links = [];

    public string $profile_visibility = 'public';

    public bool $privacy_display_location = false;

    public bool $privacy_display_birthdate = false;

    public bool $show_in_explore = true;

    public bool $open_following = false;

    public mixed $avatar = null;

    public mixed $cover = null;

    public bool $remove_avatar = false;

    public bool $remove_cover = false;

    private const FIELD_TARGETS = [
        'name' => 'profile_modal_name',
        'display_name' => 'profile_modal_display_name',
        'bio' => 'profile_modal_bio',
        'headline' => 'profile_modal_headline',
        'pronouns' => 'profile_modal_pronouns',
        'location' => 'profile_modal_location',
        'website' => 'profile_modal_website',
        'birth_date' => 'profile_modal_birth_date',
        'gender' => 'profile_modal_gender',
        'avatar' => 'profile_modal_avatar_field',
        'cover' => 'profile_modal_cover_field',
        'social_links' => 'profile_modal_social_x',
        'social_links.x' => 'profile_modal_social_x',
        'social_links.instagram' => 'profile_modal_social_instagram',
        'social_links.tiktok' => 'profile_modal_social_tiktok',
        'social_links.youtube' => 'profile_modal_social_youtube',
        'profile_visibility' => 'profile_modal_profile_visibility',
        'privacy_display_location' => 'profile_modal_privacy_display_location',
        'privacy_display_birthdate' => 'profile_modal_privacy_display_birthdate',
        'show_in_explore' => 'profile_modal_show_in_explore',
        'open_following' => 'profile_modal_open_following',
        'remove_avatar' => 'profile_modal_remove_avatar',
        'remove_cover' => 'profile_modal_remove_cover',
    ];

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
        $this->pronouns = $user->pronouns;
        $this->location = $user->location;
        $this->website = $user->website;
        $this->birth_date = $user->birth_date?->format('Y-m-d');
        $this->gender = $user->gender;
        $this->social_links = is_array($user->social_links) ? $user->social_links : [];
        $this->profile_visibility = $user->profile_visibility ?: 'public';
        $this->privacy_display_location = (bool) $user->privacy_display_location;
        $this->privacy_display_birthdate = (bool) $user->privacy_display_birthdate;
        $this->show_in_explore = (bool) $user->show_in_explore;
        $this->open_following = (bool) $user->open_following;
    }

    public function save(UpdateProfileAction $updateProfile, SettingsService $settingsService, AuthAuditLogger $auditLogger): void
    {
        $user = $this->profileUser();
        $viewer = $this->viewer();

        abort_unless($viewer instanceof User && $viewer->is($user), 403);

        Gate::forUser($viewer)->authorize('update', $user);

        $this->normalizeForValidation();

        try {
            $validated = $this->validate();
        } catch (ValidationException $exception) {
            $this->setErrorBag($exception->validator->errors());
            $this->dispatch('profile-edit-validation-failed', target: $this->firstInvalidFieldTarget($exception->validator->errors()));

            return;
        }

        $updateProfile->handle($user, [
            'name' => $validated['name'],
            'display_name' => $validated['display_name'] ?? null,
            'bio' => $validated['bio'] ?? null,
            'headline' => $validated['headline'] ?? null,
            'pronouns' => $validated['pronouns'] ?? null,
            'location' => $validated['location'] ?? null,
            'website' => $validated['website'] ?? null,
            'birth_date' => $validated['birth_date'] ?? null,
            'gender' => $validated['gender'] ?? null,
            'social_links' => ($validated['social_links'] ?? []) !== [] ? $validated['social_links'] : null,
            'privacy_display_location' => (bool) ($validated['privacy_display_location'] ?? false),
            'privacy_display_birthdate' => (bool) ($validated['privacy_display_birthdate'] ?? false),
            'avatar' => $this->avatar instanceof UploadedFile ? $this->avatar : null,
            'cover' => $this->cover instanceof UploadedFile ? $this->cover : null,
            'remove_avatar' => (bool) ($validated['remove_avatar'] ?? false),
            'remove_cover' => (bool) ($validated['remove_cover'] ?? false),
        ]);

        $settingsService->savePrivacySettings($user, [
            'profile_visibility' => $validated['profile_visibility'],
            'show_in_explore' => (bool) ($validated['show_in_explore'] ?? false),
            'open_following' => (bool) ($validated['open_following'] ?? false),
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
            'pronouns' => ['nullable', 'string', 'max:32'],
            'location' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', 'string', 'in:male,female,other,prefer_not_to_say'],
            'social_links' => ['nullable', 'array', 'max:6'],
            'social_links.x' => ['nullable', 'url', 'max:255'],
            'social_links.instagram' => ['nullable', 'url', 'max:255'],
            'social_links.tiktok' => ['nullable', 'url', 'max:255'],
            'social_links.youtube' => ['nullable', 'url', 'max:255'],
            'profile_visibility' => ['required', 'string', 'in:public,followers_only,private'],
            'privacy_display_location' => ['boolean'],
            'privacy_display_birthdate' => ['boolean'],
            'show_in_explore' => ['boolean'],
            'open_following' => ['boolean'],
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
            'social_links.*.url' => 'Enter a full social profile URL.',
            'profile_visibility.in' => 'Select a valid profile visibility setting.',
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
        $this->pronouns = $this->nullableString($this->pronouns);
        $this->location = $this->nullableString($this->location);
        $this->birth_date = $this->nullableString($this->birth_date);
        $this->gender = $this->nullableString($this->gender);

        $website = $this->nullableString($this->website);

        if ($website !== null && ! preg_match('/^https?:\/\//i', $website)) {
            $website = 'https://'.$website;
        }

        $this->website = $website;
        $this->social_links = $this->normalizeSocialLinks($this->social_links);
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
            'profile_modal_pronouns',
            'profile_modal_location',
            'profile_modal_website',
            'profile_modal_birth_date',
            'profile_modal_gender',
            'profile_modal_pets',
            'profile_modal_following',
            'profile_modal_social_x',
            'profile_modal_social_instagram',
            'profile_modal_social_tiktok',
            'profile_modal_social_youtube',
            'profile_modal_profile_visibility',
            'profile_modal_privacy_display_location',
            'profile_modal_privacy_display_birthdate',
            'profile_modal_show_in_explore',
            'profile_modal_open_following',
        ];

        return in_array($target, $allowedTargets, true) ? $target : null;
    }

    /**
     * @param  array<string, mixed>  $links
     * @return array<string, string>
     */
    private function normalizeSocialLinks(array $links): array
    {
        $normalized = [];

        foreach (['x', 'instagram', 'tiktok', 'youtube'] as $key) {
            $value = $this->nullableString($links[$key] ?? null);

            if ($value === null) {
                continue;
            }

            if (! preg_match('/^https?:\/\//i', $value)) {
                $value = 'https://'.$value;
            }

            $normalized[$key] = $value;
        }

        return $normalized;
    }

    private function firstInvalidFieldTarget(MessageBag $errors): ?string
    {
        $firstField = $errors->keys()[0] ?? null;

        if (! is_string($firstField)) {
            return null;
        }

        return self::FIELD_TARGETS[$firstField] ?? null;
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
            'pronouns',
            'location',
            'website',
            'birth_date',
            'gender',
            'social_links',
            'privacy_display_location',
            'privacy_display_birthdate',
            'profile_visibility',
            'show_in_explore',
            'open_following',
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
 scrollToTarget(targetId) {
 const target = targetId ? document.getElementById(targetId) : this.$el.querySelector('[aria-invalid=\'true\']');

 if (! target) {
 return;
 }

 target.scrollIntoView({ behavior: 'smooth', block: 'center' });

 const focusableTarget = target.matches('input, textarea, select, button, a')
 ? target
 : target.querySelector('input, textarea, select, button, a');

 window.setTimeout(() => focusableTarget?.focus({ preventScroll: true }), 350);
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
 @profile-edit-validation-failed.window="scrollToTarget($event.detail.target)"
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

 <form wire:submit.prevent="save" enctype="multipart/form-data" novalidate class="flex min-h-0 flex-1 flex-col" data-ui="profile-edit-modal-form">
 <div class="min-h-0 flex-1 overflow-y-auto scroll-smooth px-4 py-5 sm:px-6" data-ui="profile-edit-modal-scroll">
 <div class="space-y-6">
 <section class="space-y-4 rounded-[var(--radius-card)] border border-whisker/40 bg-cream/25 p-4" data-ui="profile-edit-modal-section-basic" aria-labelledby="profile-edit-basic-title">
 <div>
 <h3 id="profile-edit-basic-title" class="font-display text-base font-bold text-bark">Basic Information</h3>
 <p class="mt-1 text-sm leading-6 text-fur">Set the public identity details shown around your profile.</p>
 </div>

 <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
 <x-ui.input id="profile_modal_name" name="name" label="Name" :value="$name" required autocomplete="name" :error="$errors->first('name')" wire:model.live.blur="name"/>
 <x-ui.input id="profile_modal_display_name" name="display_name" label="Display name" :value="$display_name" autocomplete="nickname" :error="$errors->first('display_name')" wire:model.live.blur="display_name"/>
 <div class="sm:col-span-2">
 <x-ui.textarea id="profile_modal_bio" name="bio" rows="4" label="Bio" :value="$bio" maxlength="1000" hint="Brief description for your profile." :error="$errors->first('bio')" wire:model.live.blur="bio"/>
 </div>
 <div class="sm:col-span-2">
 <x-ui.input id="profile_modal_headline" name="headline" label="Headline" :value="$headline" hint="Short status or tagline shown near your name." :error="$errors->first('headline')" wire:model.live.blur="headline"/>
 </div>
 <x-ui.input id="profile_modal_pronouns" name="pronouns" label="Pronouns" :value="$pronouns" placeholder="she/her, he/him, they/them" :error="$errors->first('pronouns')" wire:model.live.blur="pronouns"/>
 <x-ui.select
 id="profile_modal_gender"
 name="gender"
 label="Gender"
 :options="[
 '' => 'Select...',
 'male' => 'Male',
 'female' => 'Female',
 'other' => 'Other',
 'prefer_not_to_say' => 'Prefer not to say',
 ]"
 :selected="$gender"
 :error="$errors->first('gender')"
 wire:model.live.blur="gender"
 />
 <x-ui.input id="profile_modal_location" name="location" label="Location" :value="$location" :error="$errors->first('location')" wire:model.live.blur="location"/>
 <x-ui.input id="profile_modal_website" name="website" type="url" label="Website" :value="$website" :error="$errors->first('website')" wire:model.live.blur="website"/>
 <x-ui.input id="profile_modal_birth_date" name="birth_date" type="date" label="Birth Date" :value="$birth_date" :error="$errors->first('birth_date')" wire:model.live.blur="birth_date"/>
 </div>
 </section>

 <section class="space-y-4 rounded-[var(--radius-card)] border border-whisker/40 bg-cream/25 p-4" data-ui="profile-edit-modal-section-media" aria-labelledby="profile-edit-media-title">
 <div>
 <h3 id="profile-edit-media-title" class="font-display text-base font-bold text-bark">Profile Media</h3>
 <p class="mt-1 text-sm leading-6 text-fur">Update the avatar and cover image that frame your profile.</p>
 </div>

 <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
 <div id="profile_modal_avatar_field" class="space-y-3 rounded-[var(--radius-card)] border border-whisker/40 bg-warm-white p-4" data-ui="profile-modal-avatar-field">
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

 <div id="profile_modal_cover_field" class="space-y-3 rounded-[var(--radius-card)] border border-whisker/40 bg-warm-white p-4" data-ui="profile-modal-cover-field">
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
 </div>
 </section>

 <section class="space-y-4 rounded-[var(--radius-card)] border border-whisker/40 bg-cream/25 p-4" data-ui="profile-edit-modal-section-social" aria-labelledby="profile-edit-social-title">
 <div>
 <h3 id="profile-edit-social-title" class="font-display text-base font-bold text-bark">Social Links</h3>
 <p class="mt-1 text-sm leading-6 text-fur">Add the public profiles visitors can use to recognize your presence elsewhere.</p>
 </div>

 <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
 <x-ui.input id="profile_modal_social_x" name="social_links[x]" type="url" label="X / Twitter" :value="$social_links['x'] ?? null" placeholder="https://x.com/username" :error="$errors->first('social_links.x')" wire:model.live.blur="social_links.x"/>
 <x-ui.input id="profile_modal_social_instagram" name="social_links[instagram]" type="url" label="Instagram" :value="$social_links['instagram'] ?? null" placeholder="https://instagram.com/username" :error="$errors->first('social_links.instagram')" wire:model.live.blur="social_links.instagram"/>
 <x-ui.input id="profile_modal_social_tiktok" name="social_links[tiktok]" type="url" label="TikTok" :value="$social_links['tiktok'] ?? null" placeholder="https://tiktok.com/@username" :error="$errors->first('social_links.tiktok')" wire:model.live.blur="social_links.tiktok"/>
 <x-ui.input id="profile_modal_social_youtube" name="social_links[youtube]" type="url" label="YouTube" :value="$social_links['youtube'] ?? null" placeholder="https://youtube.com/@username" :error="$errors->first('social_links.youtube')" wire:model.live.blur="social_links.youtube"/>
 </div>
 </section>

 <section class="space-y-4 rounded-[var(--radius-card)] border border-whisker/40 bg-cream/25 p-4" data-ui="profile-edit-modal-section-privacy" aria-labelledby="profile-edit-privacy-title">
 <div>
 <h3 id="profile-edit-privacy-title" class="font-display text-base font-bold text-bark">Privacy</h3>
 <p class="mt-1 text-sm leading-6 text-fur">Choose how much of your profile is visible to other members.</p>
 </div>

 <x-ui.select
 id="profile_modal_profile_visibility"
 name="profile_visibility"
 label="Profile visibility"
 :options="[
 'public' => 'Public',
 'followers_only' => 'Followers only',
 'private' => 'Private',
 ]"
 :selected="$profile_visibility"
 :error="$errors->first('profile_visibility')"
 required
 wire:model.live.blur="profile_visibility"
 />

 <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
 <div id="profile_modal_privacy_display_location" class="rounded-[var(--radius-soft)] border border-whisker/40 bg-warm-white p-3">
 <x-ui.checkbox name="privacy_display_location" label="Show location on profile" description="Visitors only see this when profile visibility also allows it." :checked="$privacy_display_location" wire:model="privacy_display_location"/>
 </div>
 <div id="profile_modal_privacy_display_birthdate" class="rounded-[var(--radius-soft)] border border-whisker/40 bg-warm-white p-3">
 <x-ui.checkbox name="privacy_display_birthdate" label="Show age on profile" description="Only your calculated age is shown, never your birth date." :checked="$privacy_display_birthdate" wire:model="privacy_display_birthdate"/>
 </div>
 <div id="profile_modal_show_in_explore" class="rounded-[var(--radius-soft)] border border-whisker/40 bg-warm-white p-3">
 <x-ui.checkbox name="show_in_explore" label="Show in Explore" description="Allow your profile to be recommended to other members." :checked="$show_in_explore" wire:model="show_in_explore"/>
 </div>
 <div id="profile_modal_open_following" class="rounded-[var(--radius-soft)] border border-whisker/40 bg-warm-white p-3">
 <x-ui.checkbox name="open_following" label="Open following list" description="Allow others to see who you follow when profile visibility permits it." :checked="$open_following" wire:model="open_following"/>
 </div>
 </div>
 </section>

 <section class="grid grid-cols-1 gap-4 sm:grid-cols-2" data-ui="profile-edit-modal-section-completion" aria-label="Profile completion shortcuts">
 <div id="profile_modal_pets" class="flex flex-col gap-2 rounded-[var(--radius-card)] border border-whisker/40 bg-cream/30 p-4" data-ui="profile-modal-pets-field">
 <p class="text-sm font-semibold text-bark">Pets</p>
 <p class="text-xs leading-5 text-fur">Add a pet profile from the Pets tab so visitors can meet your companion without leaving your profile.</p>
 </div>

 <div id="profile_modal_following" class="flex flex-col gap-2 rounded-[var(--radius-card)] border border-whisker/40 bg-cream/30 p-4" data-ui="profile-modal-following-field">
 <p class="text-sm font-semibold text-bark">Following</p>
 <p class="text-xs leading-5 text-fur">Follow a few members from profile suggestions to personalize your community graph.</p>
 </div>
 </section>
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
