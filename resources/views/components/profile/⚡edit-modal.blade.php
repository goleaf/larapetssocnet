<?php

use App\Actions\Users\UpdateProfileAction;
use App\Exceptions\UsernameChangeCooldownException;
use App\Exceptions\UsernameNotAvailableException;
use App\Exceptions\UsernameReservedException;
use App\Models\Identity\User;
use App\Services\Auth\AuthAuditLogger;
use App\Services\LocationAutocompleteService;
use App\Services\SettingsService;
use App\Support\Usernames\UsernameNormalizer;
use App\Support\Usernames\UsernameRules;
use Carbon\CarbonImmutable;
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

    public string $username = '';

    public string $currentUsername = '';

    public ?string $usernameStatus = null;

    public string $usernameMessage = '';

    public bool $usernameChangeLocked = false;

    public int $usernameChangeDaysRemaining = 0;

    public ?string $display_name = null;

    public ?string $bio = null;

    public ?string $headline = null;

    public ?string $pronouns = null;

    public ?string $location = null;

    public ?string $location_lat = null;

    public ?string $location_lng = null;

    /**
     * @var list<array{label: string, latitude: float, longitude: float}>
     */
    public array $locationSuggestions = [];

    public bool $locationSuggestionsOpen = false;

    public ?string $website = null;

    public ?string $birth_date = null;

    public string $birth_day = '';

    public string $birth_month = '';

    public string $birth_year = '';

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
        'username' => 'profile_modal_username',
        'display_name' => 'profile_modal_display_name',
        'bio' => 'profile_modal_bio',
        'headline' => 'profile_modal_headline',
        'pronouns' => 'profile_modal_pronouns',
        'location' => 'profile_modal_location',
        'location_lat' => 'profile_modal_location',
        'location_lng' => 'profile_modal_location',
        'website' => 'profile_modal_website',
        'birth_date' => 'profile_modal_birth_date',
        'birth_day' => 'profile_modal_birth_date',
        'birth_month' => 'profile_modal_birth_date',
        'birth_year' => 'profile_modal_birth_date',
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
        $this->username = (string) $user->username;
        $this->currentUsername = (string) $user->username;
        $this->usernameChangeLocked = ! $user->canChangeUsername();
        $this->usernameChangeDaysRemaining = $user->daysUntilUsernameChange();
        $this->refreshUsernameAvailability();
        $this->display_name = $user->display_name;
        $this->bio = $user->bio;
        $this->headline = $user->headline;
        $this->pronouns = $user->pronouns;
        $this->location = $user->location;
        $this->location_lat = $user->location_lat !== null ? (string) $user->location_lat : null;
        $this->location_lng = $user->location_lng !== null ? (string) $user->location_lng : null;
        $this->website = $user->website;
        $this->birth_date = $user->birth_date?->format('Y-m-d');
        $this->setBirthDateParts($user);
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

        try {
            $updateProfile->handle($user, [
                'name' => $validated['name'],
                'username' => $validated['username'],
                'display_name' => $validated['display_name'] ?? null,
                'bio' => $validated['bio'] ?? null,
                'headline' => $validated['headline'] ?? null,
                'pronouns' => $validated['pronouns'] ?? null,
                'location' => $validated['location'] ?? null,
                'location_lat' => $validated['location_lat'] ?? null,
                'location_lng' => $validated['location_lng'] ?? null,
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
        } catch (UsernameChangeCooldownException|UsernameNotAvailableException|UsernameReservedException $exception) {
            $this->addError('username', $exception->getMessage());
            $this->dispatch('profile-edit-validation-failed', target: self::FIELD_TARGETS['username']);

            return;
        }

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

    public function updatedUsername(): void
    {
        $this->username = UsernameNormalizer::normalize($this->username);
        $this->refreshUsernameAvailability();
    }

    public function updatedLocation(): void
    {
        $this->location = $this->nullableString($this->location);
        $this->location_lat = null;
        $this->location_lng = null;

        if ($this->location === null || mb_strlen($this->location) < 2) {
            $this->locationSuggestions = [];
            $this->locationSuggestionsOpen = false;

            return;
        }

        $this->locationSuggestions = app(LocationAutocompleteService::class)
            ->suggest($this->location, (int) config('services.geocoding.limit', 5));
        $this->locationSuggestionsOpen = $this->locationSuggestions !== [];
    }

    public function selectLocationSuggestion(int $index): void
    {
        $suggestion = $this->locationSuggestions[$index] ?? null;

        if (! is_array($suggestion)) {
            return;
        }

        $this->location = (string) $suggestion['label'];
        $this->location_lat = (string) $suggestion['latitude'];
        $this->location_lng = (string) $suggestion['longitude'];
        $this->locationSuggestionsOpen = false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => UsernameRules::requiredRules($this->userId),
            'display_name' => ['nullable', 'string', 'max:50'],
            'bio' => ['nullable', 'string', 'max:160'],
            'headline' => ['nullable', 'string', 'max:120'],
            'pronouns' => ['nullable', 'string', 'max:32'],
            'location' => ['nullable', 'string', 'max:255'],
            'location_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'location_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'website' => ['nullable', 'url', 'max:255'],
            'birth_day' => ['nullable', 'integer', 'between:1,31'],
            'birth_month' => ['nullable', 'integer', 'between:1,12'],
            'birth_year' => ['nullable', 'integer', 'between:'.(now()->year - 100).','.now()->year],
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
            'username.min' => 'Username must be '.UsernameRules::minLength().'-'.UsernameRules::maxLength().' characters.',
            'username.max' => 'Username must be '.UsernameRules::minLength().'-'.UsernameRules::maxLength().' characters.',
            'username.regex' => 'Only letters, numbers and underscores allowed.',
            'username.unique' => 'Username is already taken.',
            'display_name.max' => 'Display name must be 50 characters or fewer.',
            'bio.max' => 'Bio must be 160 characters or fewer.',
            'location_lat.between' => 'Select a valid location suggestion.',
            'location_lng.between' => 'Select a valid location suggestion.',
            'birth_date.date' => 'Enter a valid date of birth.',
            'birth_date.before' => 'Date of birth must be before today.',
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
        $this->username = UsernameNormalizer::normalize($this->username);
        $this->display_name = $this->nullableString($this->display_name);
        $this->bio = $this->nullableString($this->bio);
        $this->headline = $this->nullableString($this->headline);
        $this->pronouns = $this->nullableString($this->pronouns);
        $this->location = $this->nullableString($this->location);
        $this->location_lat = $this->location !== null ? $this->nullableString($this->location_lat) : null;
        $this->location_lng = $this->location !== null ? $this->nullableString($this->location_lng) : null;
        $this->birth_date = $this->nullableString($this->birth_date);
        $this->gender = $this->nullableString($this->gender);
        $this->birth_date = $this->composeBirthDate();

        $website = $this->nullableString($this->website);

        if ($website !== null && ! preg_match('/^https?:\/\//i', $website)) {
            $website = 'https://'.$website;
        }

        $this->website = $website;
        $this->social_links = $this->normalizeSocialLinks($this->social_links);
        $this->refreshUsernameAvailability();
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function setBirthDateParts(User $user): void
    {
        if ($user->birth_date === null) {
            $this->birth_day = '';
            $this->birth_month = '';
            $this->birth_year = '';

            return;
        }

        $this->birth_day = (string) $user->birth_date->day;
        $this->birth_month = (string) $user->birth_date->month;
        $this->birth_year = (string) $user->birth_date->year;
    }

    private function composeBirthDate(): ?string
    {
        $birthDay = $this->nullableString($this->birth_day);
        $birthMonth = $this->nullableString($this->birth_month);
        $birthYear = $this->nullableString($this->birth_year);

        if ($birthDay === null && $birthMonth === null && $birthYear === null) {
            return null;
        }

        if ($birthDay === null || $birthMonth === null || $birthYear === null) {
            return 'invalid-date';
        }

        $day = (int) $birthDay;
        $month = (int) $birthMonth;
        $year = (int) $birthYear;

        if (! checkdate($month, $day, $year)) {
            return 'invalid-date';
        }

        return CarbonImmutable::create($year, $month, $day)->toDateString();
    }

    private function refreshUsernameAvailability(): void
    {
        $normalizedUsername = UsernameNormalizer::normalize($this->username);
        $normalizedCurrentUsername = UsernameNormalizer::normalize($this->currentUsername);

        if ($normalizedUsername === '') {
            $this->usernameStatus = null;
            $this->usernameMessage = '';

            return;
        }

        if ($normalizedUsername === $normalizedCurrentUsername) {
            $this->usernameStatus = 'ok';
            $this->usernameMessage = 'Current username.';

            return;
        }

        if ($this->usernameChangeLocked) {
            $this->usernameStatus = 'locked';
            $this->usernameMessage = $this->usernameCooldownMessage();

            return;
        }

        $firstError = UsernameRules::firstError($normalizedUsername, $this->userId);

        $this->usernameStatus = $firstError === null ? 'ok' : 'taken';
        $this->usernameMessage = $firstError ?? 'Username is available!';
    }

    public function usernameCooldownMessage(): string
    {
        $cooldownDays = (int) config('usernames.cooldown_days', 30);

        return "You can only change your username once every {$cooldownDays} days. Your next change is available in {$this->usernameChangeDaysRemaining} days.";
    }

    private function sanitizeFocusTarget(?string $target): ?string
    {
        $target = trim((string) $target);

        $allowedTargets = [
            'profile_modal_avatar_field',
            'profile_modal_cover_field',
            'profile_modal_name',
            'profile_modal_username',
            'profile_modal_display_name',
            'profile_modal_bio',
            'profile_modal_headline',
            'profile_modal_pronouns',
            'profile_modal_location',
            'profile_modal_website',
            'profile_modal_birth_date',
            'profile_modal_birth_day',
            'profile_modal_birth_month',
            'profile_modal_birth_year',
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
            'username',
            'display_name',
            'bio',
            'headline',
            'pronouns',
            'location',
            'location_lat',
            'location_lng',
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
 $avatarTemporaryUrl = null;
 $coverTemporaryUrl = null;

 if (is_object($avatar) && method_exists($avatar, 'temporaryUrl')) {
 try {
 $avatarTemporaryUrl = $avatar->temporaryUrl();
 } catch (\Throwable) {
 $avatarTemporaryUrl = null;
 }
 }

 if (is_object($cover) && method_exists($cover, 'temporaryUrl')) {
 try {
 $coverTemporaryUrl = $cover->temporaryUrl();
 } catch (\Throwable) {
 $coverTemporaryUrl = null;
 }
 }

 $avatarPreviewUrl = $avatarTemporaryUrl ?: $user->avatar_url;
 $coverPreviewUrl = $coverTemporaryUrl ?: $coverUrl;
 $currentYear = now()->year;
 $monthOptions = [
  1 => 'January',
  2 => 'February',
  3 => 'March',
  4 => 'April',
  5 => 'May',
  6 => 'June',
  7 => 'July',
  8 => 'August',
  9 => 'September',
  10 => 'October',
  11 => 'November',
  12 => 'December',
 ];
@endphp

<div
 data-ui="profile-edit-modal"
 class="fixed inset-0 z-50"
 x-data="{
 focusTarget: @js($focusTarget),
 displayNameCount: @js(mb_strlen((string) ($display_name ?? ''))),
 bioRemaining: @js(160 - mb_strlen((string) ($bio ?? ''))),
 autoGrow(target) {
 target.style.height = 'auto';
 target.style.height = `${target.scrollHeight}px`;
 },
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
 <div>
 <x-ui.input
 id="profile_modal_display_name"
 name="display_name"
 label="Display name"
 :value="$display_name"
 autocomplete="nickname"
 maxlength="50"
 :error="$errors->first('display_name')"
 wire:model.live.debounce.300ms="display_name"
 x-on:input="displayNameCount = $event.target.value.length"
 />
 <p class="mt-1 text-right text-xs text-fur" id="profile_modal_display_name_counter">
 <span x-text="displayNameCount"></span>/50
 </p>
 </div>
 <div class="sm:col-span-2">
 <label for="profile_modal_username" class="text-sm font-semibold text-bark">Username <span class="text-danger" aria-hidden="true">*</span></label>
 <div class="relative mt-1">
 <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-fur" aria-hidden="true">@</span>
 <input
 id="profile_modal_username"
 name="username"
 type="text"
 autocomplete="username"
 required
 maxlength="30"
 value="{{ $username }}"
 wire:model.live.debounce.600ms="username"
 class="form-input h-[var(--control-height-md)] w-full pl-10 pr-12 text-sm focus:border-paw @error('username') border-rose text-rose focus:border-rose @enderror"
 @error('username') aria-invalid="true" @enderror
 aria-describedby="profile_modal_username_hint"
 >
 <span class="absolute inset-y-0 right-0 flex items-center pr-3">
 <span wire:loading wire:target="username" class="text-fur" aria-label="Checking username">
 <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" aria-hidden="true">
 <circle class="opacity-25" cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3"></circle>
 <path class="opacity-75" fill="currentColor" d="M12 3a9 9 0 0 1 9 9h-3a6 6 0 0 0-6-6V3Z"></path>
 </svg>
 </span>
 <span wire:loading.remove wire:target="username">
 @if ($usernameStatus === 'ok')
 <svg class="h-5 w-5 text-success" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-label="Username available">
 <path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/>
 </svg>
 @elseif (in_array($usernameStatus, ['taken', 'locked'], true))
 <svg class="h-5 w-5 text-danger" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-label="Username unavailable">
 <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
 </svg>
 @endif
 </span>
 </span>
 </div>
 <div id="profile_modal_username_hint" class="mt-1 flex flex-col gap-1 text-xs" aria-live="polite">
 <p class="text-fur">3-30 chars. Letters, numbers, and underscores.</p>
 @if ($usernameChangeLocked)
 <p class="font-medium text-amber">{{ $this->usernameCooldownMessage() }}</p>
 @endif
 @if ($errors->has('username'))
 <p class="font-medium text-danger">{{ $errors->first('username') }}</p>
 @elseif ($usernameMessage !== '')
 <p class="font-medium @if ($usernameStatus === 'ok') text-success @elseif ($usernameStatus === 'locked') text-amber @else text-danger @endif">{{ $usernameMessage }}</p>
 @endif
 </div>
 </div>
 <div class="sm:col-span-2">
 <label for="profile_modal_bio" class="text-sm font-semibold text-bark">Bio</label>
 <textarea
 id="profile_modal_bio"
 name="bio"
 rows="3"
 maxlength="160"
 wire:model.live.debounce.300ms="bio"
 x-init="$nextTick(() => autoGrow($el))"
 x-on:input="bioRemaining = 160 - $event.target.value.length; autoGrow($event.target)"
 class="form-textarea mt-1 min-h-24 w-full resize-none overflow-hidden text-sm focus:border-paw @error('bio') border-rose text-rose focus:border-rose @enderror"
 @error('bio') aria-invalid="true" @enderror
 aria-describedby="profile_modal_bio_hint profile_modal_bio_counter"
 >{{ $bio }}</textarea>
 <div class="mt-1 flex items-start justify-between gap-3 text-xs">
 <div id="profile_modal_bio_hint">
 @error('bio')
 <p class="font-medium text-danger">{{ $message }}</p>
 @else
 <p class="text-fur">Brief description for your profile.</p>
 @enderror
 </div>
 <p id="profile_modal_bio_counter" class="shrink-0 text-fur">
 <span x-text="bioRemaining"></span> left
 </p>
 </div>
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
 <div class="relative">
 <label for="profile_modal_location" class="text-sm font-semibold text-bark">Location</label>
 <input
 id="profile_modal_location"
 name="location"
 type="text"
 value="{{ $location }}"
 autocomplete="off"
 wire:model.live.debounce.400ms="location"
 class="form-input mt-1 h-[var(--control-height-md)] w-full text-sm focus:border-paw @error('location') border-rose text-rose focus:border-rose @enderror"
 aria-autocomplete="list"
 aria-expanded="{{ $locationSuggestionsOpen ? 'true' : 'false' }}"
 aria-controls="profile_modal_location_suggestions"
 aria-describedby="profile_modal_location_hint"
 @error('location') aria-invalid="true" @enderror
 >
 <input type="hidden" name="location_lat" wire:model="location_lat">
 <input type="hidden" name="location_lng" wire:model="location_lng">
 <div class="mt-1 min-h-5 text-xs" id="profile_modal_location_hint" aria-live="polite">
 <span wire:loading wire:target="location" class="text-fur">Searching locations...</span>
 @error('location')
 <span wire:loading.remove wire:target="location" class="font-medium text-danger">{{ $message }}</span>
 @else
 @if ($errors->has('location_lat') || $errors->has('location_lng'))
 <span wire:loading.remove wire:target="location" class="font-medium text-danger">Select a valid location suggestion.</span>
 @endif
 @enderror
 </div>
 @if ($locationSuggestionsOpen && $locationSuggestions !== [])
 <ul id="profile_modal_location_suggestions" class="absolute z-20 mt-1 max-h-56 w-full overflow-y-auto rounded-[var(--radius-control)] border border-whisker/50 bg-warm-white py-1 shadow-card" role="listbox">
 @foreach ($locationSuggestions as $index => $suggestion)
 <li role="option" wire:key="profile-location-suggestion-{{ $index }}">
 <button type="button" class="flex w-full flex-col items-start gap-0.5 px-3 py-2 text-left text-sm text-bark transition-colors hover:bg-cream focus-visible:bg-cream focus-visible:outline-none" wire:click="selectLocationSuggestion({{ $index }})">
 <span class="font-medium">{{ $suggestion['label'] }}</span>
 <span class="text-xs text-fur">{{ number_format($suggestion['latitude'], 4) }}, {{ number_format($suggestion['longitude'], 4) }}</span>
 </button>
 </li>
 @endforeach
 </ul>
 @endif
 </div>
 <x-ui.input id="profile_modal_website" name="website" type="url" label="Website" :value="$website" :error="$errors->first('website')" wire:model.live.blur="website"/>
 <fieldset id="profile_modal_birth_date" class="sm:col-span-2 rounded-[var(--radius-card)] border border-whisker/40 bg-warm-white/70 p-4">
 <legend class="px-1 text-sm font-semibold text-bark">Date of birth</legend>
 <div class="mt-3 grid gap-3 sm:grid-cols-3">
 <x-ui.select id="profile_modal_birth_day" name="birth_day" label="Day" :options="range(1, 31)" :selected="$birth_day" placeholder="Day" :error="$errors->first('birth_day')" wire:model.live.blur="birth_day"/>
 <x-ui.select id="profile_modal_birth_month" name="birth_month" label="Month" :options="$monthOptions" :selected="$birth_month" placeholder="Month" :error="$errors->first('birth_month')" wire:model.live.blur="birth_month"/>
 <x-ui.select id="profile_modal_birth_year" name="birth_year" label="Year" :options="range($currentYear, $currentYear - 100)" :selected="$birth_year" placeholder="Year" :error="$errors->first('birth_year')" wire:model.live.blur="birth_year"/>
 </div>
 @error('birth_date')
 <p class="mt-2 text-sm font-medium text-danger">{{ $message }}</p>
 @enderror
 </fieldset>
 </div>
 </section>

 <section class="space-y-4 rounded-[var(--radius-card)] border border-whisker/40 bg-cream/25 p-4" data-ui="profile-edit-modal-section-media" aria-labelledby="profile-edit-media-title">
 <div>
 <h3 id="profile-edit-media-title" class="font-display text-base font-bold text-bark">Profile Media</h3>
 <p class="mt-1 text-sm leading-6 text-fur">Update the avatar and cover image that frame your profile.</p>
 </div>

 <div class="grid grid-cols-1 gap-4 md:grid-cols-2" data-ui="profile-media-upload-grid">
 <div id="profile_modal_avatar_field" class="flex min-w-0 flex-col gap-4 rounded-[var(--radius-card)] border border-whisker/40 bg-warm-white p-4" data-ui="profile-avatar-upload-panel">
 <div class="flex items-start justify-between gap-3">
 <div class="min-w-0">
 <h4 class="text-sm font-bold text-bark">Avatar</h4>
 <p class="mt-1 text-xs leading-5 text-fur">Shown beside posts, comments, profile lists, and messages.</p>
 </div>
 <span class="ui-token shrink-0">Square</span>
 </div>

 <div class="flex items-center gap-4">
 <div class="relative h-24 w-24 shrink-0 overflow-hidden rounded-pill border-4 border-warm-white bg-cream shadow-sm ring-1 ring-whisker/50" data-ui="profile-avatar-preview">
 @if ($avatarPreviewUrl)
 <img src="{{ $avatarPreviewUrl }}" alt="{{ $user->name }} avatar preview" class="h-full w-full object-cover">
 @else
 <div class="{{ $user->profile_default_avatar_color }} flex h-full w-full items-center justify-center font-display text-3xl font-bold uppercase" aria-label="{{ $user->name }} avatar initial" role="img">
 {{ $user->profile_initial }}
 </div>
 @endif
 <div wire:loading.flex wire:target="avatar" class="absolute inset-0 items-center justify-center bg-warm-white/75 text-xs font-semibold text-bark">
 Previewing
 </div>
 </div>
 <div class="min-w-0 text-xs leading-5 text-fur">
 <p>Use a clear face or pet portrait. The crop is circular across the app.</p>
 @if ($avatarTemporaryUrl)
 <p class="mt-1 font-semibold text-success" role="status">New avatar selected.</p>
 @endif
 </div>
 </div>

 <x-ui.file-upload
 id="profile_modal_avatar"
 name="avatar"
 label="Upload avatar"
 accept="image/jpeg,image/png,image/webp"
 maxSize="10MB"
 preview
 help="JPG, PNG, or WEBP. Square image recommended."
 :error="$errors->first('avatar')"
 wire:model="avatar"
 />
 @if ($user->avatar_url)
 <div class="rounded-[var(--radius-soft)] border border-whisker/40 bg-cream/35 p-3">
 <x-ui.checkbox id="profile_modal_remove_avatar" name="remove_avatar" label="Remove current avatar" wire:model="remove_avatar"/>
 </div>
 @endif
 </div>

 <div id="profile_modal_cover_field" class="flex min-w-0 flex-col gap-4 rounded-[var(--radius-card)] border border-whisker/40 bg-warm-white p-4" data-ui="profile-cover-upload-panel">
 <div class="flex items-start justify-between gap-3">
 <div class="min-w-0">
 <h4 class="text-sm font-bold text-bark">Cover Photo</h4>
 <p class="mt-1 text-xs leading-5 text-fur">This banner frames the top of your profile on desktop and mobile.</p>
 </div>
 <span class="ui-token shrink-0">Wide</span>
 </div>

 <div class="relative aspect-[16/7] w-full overflow-hidden rounded-[var(--radius-soft)] border border-whisker/50 bg-cream" data-ui="profile-cover-preview">
 @if ($coverPreviewUrl)
 <img src="{{ $coverPreviewUrl }}" alt="{{ $user->name }} cover photo preview" class="h-full w-full object-cover">
 @else
 <div class="{{ $user->profile_default_gradient }} h-full w-full"></div>
 @endif
 <div class="absolute inset-x-0 bottom-0 bg-bark/45 px-3 py-2 text-xs font-semibold text-warm-white">
 {{ $coverTemporaryUrl ? 'New cover selected' : 'Current cover preview' }}
 </div>
 <div wire:loading.flex wire:target="cover" class="absolute inset-0 items-center justify-center bg-warm-white/75 text-xs font-semibold text-bark">
 Previewing
 </div>
 </div>

 <x-ui.file-upload
 id="profile_modal_cover"
 name="cover"
 label="Upload cover photo"
 accept="image/jpeg,image/png,image/webp,image/gif"
 maxSize="5MB"
 preview
 help="JPG, PNG, WEBP, or GIF. Recommended 1600x480."
 :error="$errors->first('cover')"
 wire:model="cover"
 />
 @if ($coverUrl)
 <div class="rounded-[var(--radius-soft)] border border-whisker/40 bg-cream/35 p-3">
 <x-ui.checkbox id="profile_modal_remove_cover" name="remove_cover" label="Remove current cover photo" wire:model="remove_cover"/>
 </div>
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
