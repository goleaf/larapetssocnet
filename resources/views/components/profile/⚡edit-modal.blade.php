<?php

use App\Actions\Users\UpdateProfileAction;
use App\Enums\ProfileVisibility;
use App\Exceptions\UsernameChangeCooldownException;
use App\Exceptions\UsernameNotAvailableException;
use App\Exceptions\UsernameReservedException;
use App\Http\Requests\Profile\UpdateProfileModalRequest;
use App\Models\Identity\User;
use App\Services\Auth\AuthAuditLogger;
use App\Services\LocationAutocompleteService;
use App\Services\SettingsService;
use App\Support\Profiles\SocialLinkNormalizer;
use App\Support\Usernames\UsernameNormalizer;
use App\Support\Usernames\UsernameRules;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;
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
     * @var array{x?: string|null, instagram?: string|null, facebook?: string|null, youtube?: string|null}
     */
    public array $social_links = [];

    public string $profile_visibility = 'public';

    public bool $account_is_private = false;

    public bool $privacy_display_birthdate = false;

    public bool $privacy_display_email = false;

    public mixed $avatar = null;

    public mixed $cover = null;

    public float $cover_photo_position = User::DEFAULT_COVER_PHOTO_POSITION;

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
        'cover_photo_position' => 'profile_modal_cover_field',
        'social_links' => 'profile_modal_social_x',
        'social_links.x' => 'profile_modal_social_x',
        'social_links.instagram' => 'profile_modal_social_instagram',
        'social_links.facebook' => 'profile_modal_social_facebook',
        'social_links.youtube' => 'profile_modal_social_youtube',
        'account_is_private' => 'profile_modal_account_visibility',
        'privacy_display_birthdate' => 'profile_modal_privacy_display_birthdate',
        'privacy_display_email' => 'profile_modal_privacy_display_email',
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
        $this->social_links = SocialLinkNormalizer::editable($user->social_links);
        $this->profile_visibility = $user->profileVisibility()->value;
        $this->account_is_private = $user->profileVisibility()->marksAccountPrivate();
        $this->privacy_display_birthdate = (bool) $user->privacy_display_birthdate;
        $this->privacy_display_email = (bool) $user->privacy_display_email;
        $this->cover_photo_position = $user->coverPhotoPositionPercentage();
    }

    public function save(UpdateProfileAction $updateProfile, AuthAuditLogger $auditLogger): void
    {
        [$user, $viewer] = $this->authorizeProfileOwnerUpdate();

        try {
            $validated = UpdateProfileModalRequest::validateForLivewire($user, $viewer, $this->profileInput());
        } catch (ValidationException $exception) {
            $this->setErrorBag($exception->validator->errors());
            $this->dispatch('profile-edit-validation-failed', target: $this->firstInvalidFieldTarget($exception->validator->errors()));

            return;
        }

        $oldUsername = (string) $user->username;
        $this->syncValidatedProfileState($validated);

        try {
            $updatedUser = $updateProfile->handle($user, [
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
                'avatar' => $this->avatar instanceof UploadedFile ? $this->avatar : null,
                'cover' => $this->cover instanceof UploadedFile ? $this->cover : null,
                'cover_photo_position' => (float) ($validated['cover_photo_position'] ?? User::DEFAULT_COVER_PHOTO_POSITION),
                'remove_avatar' => (bool) ($validated['remove_avatar'] ?? false),
                'remove_cover' => (bool) ($validated['remove_cover'] ?? false),
            ]);
        } catch (UsernameChangeCooldownException|UsernameNotAvailableException|UsernameReservedException $exception) {
            $this->addError('username', $exception->getMessage());
            $this->dispatch('profile-edit-validation-failed', target: self::FIELD_TARGETS['username']);

            return;
        }

        $changedFields = $this->changedFields($validated);

        $auditLogger->record($viewer, 'profile_updated', request(), [
            'changed_fields' => $changedFields,
            'changed_field_count' => count($changedFields),
        ]);

        $this->reset(['avatar', 'cover', 'remove_avatar', 'remove_cover']);
        $this->resetValidation();

        $this->js("document.body.classList.remove('overflow-hidden')");
        $this->dispatch('profile-edit-saved');
        $this->dispatch('profile-toast', message: 'Profile updated successfully.', type: 'success');

        if ($oldUsername !== (string) $updatedUser->username) {
            $this->dispatch(
                'profile-browser-url-replace-requested',
                url: route('profile.show', ['user' => $updatedUser->username], false),
                username: (string) $updatedUser->username,
            );
        }
    }

    public function updateAccountVisibility(mixed $private, SettingsService $settingsService, AuthAuditLogger $auditLogger): void
    {
        [$user, $viewer] = $this->authorizeProfileOwnerUpdate();
        $isPrivate = $this->validatedBoolean($private, 'account_is_private');
        $visibility = $isPrivate ? ProfileVisibility::FollowersOnly : ProfileVisibility::Public;

        $settingsService->savePrivacySettings($user, [
            'profile_visibility' => $visibility->value,
        ]);

        $user->refresh();
        $this->profile_visibility = $user->profileVisibility()->value;
        $this->account_is_private = $user->profileVisibility()->marksAccountPrivate();

        $this->recordPrivacyToggleAudit($auditLogger, $viewer, 'account_visibility', $visibility->value);

        $this->dispatch(
            'profile-privacy-setting-saved',
            setting: 'account_visibility',
            value: $visibility->value,
        );
    }

    public function updateShowAge(mixed $showAge, AuthAuditLogger $auditLogger): void
    {
        [$user, $viewer] = $this->authorizeProfileOwnerUpdate();
        $value = $this->validatedBoolean($showAge, 'privacy_display_birthdate');

        $user->forceFill([
            'privacy_display_birthdate' => $value,
        ])->save();

        $this->privacy_display_birthdate = $value;

        $this->recordPrivacyToggleAudit($auditLogger, $viewer, 'privacy_display_birthdate', $value);

        $this->dispatch(
            'profile-privacy-setting-saved',
            setting: 'privacy_display_birthdate',
            value: $value,
        );
    }

    public function updateEmailDiscovery(mixed $allowEmailDiscovery, AuthAuditLogger $auditLogger): void
    {
        [$user, $viewer] = $this->authorizeProfileOwnerUpdate();
        $value = $this->validatedBoolean($allowEmailDiscovery, 'privacy_display_email');

        $user->forceFill([
            'privacy_display_email' => $value,
        ])->save();

        $this->privacy_display_email = $value;

        $this->recordPrivacyToggleAudit($auditLogger, $viewer, 'privacy_display_email', $value);

        $this->dispatch(
            'profile-privacy-setting-saved',
            setting: 'privacy_display_email',
            value: $value,
        );
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

    public function updatedWebsite(): void
    {
        $this->website = SocialLinkNormalizer::normalizeUrl($this->website);
        $this->validateOnly('website');
    }

    public function updatedSocialLinks(mixed $value, ?string $key = null): void
    {
        if (! is_string($key)) {
            return;
        }

        if (in_array($key, ['x', 'instagram'], true)) {
            $this->social_links[$key] = SocialLinkNormalizer::normalizeHandle($key, $value);
        } elseif (in_array($key, ['facebook', 'youtube'], true)) {
            $this->social_links[$key] = SocialLinkNormalizer::normalizeUrl($value);
        } else {
            return;
        }

        $this->validateOnly('social_links.'.$key);
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
        $rules = UpdateProfileModalRequest::rulesFor($this->profileUser());
        unset($rules['bio_html']);

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return UpdateProfileModalRequest::messagesForValidation();
    }

    /**
     * @return array{0: User, 1: User}
     */
    private function authorizeProfileOwnerUpdate(): array
    {
        $user = $this->profileUser();
        $viewer = $this->viewer();

        abort_unless($viewer instanceof User && $viewer->is($user), 403);

        Gate::forUser($viewer)->authorize('update', $user);

        return [$user, $viewer];
    }

    private function validatedBoolean(mixed $value, string $field): bool
    {
        $validated = Validator::make(
            [$field => $value],
            [$field => ['present', 'boolean']]
        )->validate();

        return (bool) $validated[$field];
    }

    private function recordPrivacyToggleAudit(AuthAuditLogger $auditLogger, User $viewer, string $setting, bool|string $value): void
    {
        $auditLogger->record($viewer, 'profile_privacy_setting_updated', request(), [
            'setting' => $setting,
            'value' => $value,
        ]);
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

    /**
     * @return array<string, mixed>
     */
    private function profileInput(): array
    {
        return [
            'name' => $this->name,
            'username' => $this->username,
            'display_name' => $this->display_name,
            'bio' => $this->bio,
            'headline' => $this->headline,
            'pronouns' => $this->pronouns,
            'location' => $this->location,
            'location_lat' => $this->location_lat,
            'location_lng' => $this->location_lng,
            'website' => $this->website,
            'birth_date' => $this->composeBirthDate(),
            'gender' => $this->gender,
            'social_links' => $this->social_links,
            'avatar' => $this->avatar instanceof UploadedFile ? $this->avatar : null,
            'cover' => $this->cover instanceof UploadedFile ? $this->cover : null,
            'cover_photo_position' => $this->cover_photo_position,
            'remove_avatar' => $this->remove_avatar,
            'remove_cover' => $this->remove_cover,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function syncValidatedProfileState(array $validated): void
    {
        $this->name = (string) $validated['name'];
        $this->username = (string) $validated['username'];
        $this->display_name = $validated['display_name'] ?? null;
        $this->bio = $validated['bio'] ?? null;
        $this->headline = $validated['headline'] ?? null;
        $this->pronouns = $validated['pronouns'] ?? null;
        $this->location = $validated['location'] ?? null;
        $this->location_lat = isset($validated['location_lat']) ? (string) $validated['location_lat'] : null;
        $this->location_lng = isset($validated['location_lng']) ? (string) $validated['location_lng'] : null;
        $this->website = $validated['website'] ?? null;
        $this->birth_date = $validated['birth_date'] ?? null;
        $this->gender = $validated['gender'] ?? null;
        $this->social_links = SocialLinkNormalizer::editable($validated['social_links'] ?? []);
        $this->cover_photo_position = (float) ($validated['cover_photo_position'] ?? User::DEFAULT_COVER_PHOTO_POSITION);
        $this->remove_avatar = (bool) ($validated['remove_avatar'] ?? false);
        $this->remove_cover = (bool) ($validated['remove_cover'] ?? false);
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
            'profile_modal_social_facebook',
            'profile_modal_social_youtube',
            'profile_modal_account_visibility',
            'profile_modal_privacy_display_birthdate',
            'profile_modal_privacy_display_email',
        ];

        return in_array($target, $allowedTargets, true) ? $target : null;
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
        ];

        if (($validated['avatar'] ?? null) instanceof UploadedFile) {
            $fields[] = 'avatar';
        }

        if (($validated['cover'] ?? null) instanceof UploadedFile) {
            $fields[] = 'cover';
            $fields[] = 'cover_photo_position';
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
 $socialInputIcons = [
  'website' => new \Illuminate\Support\HtmlString('<svg data-ui="profile-social-icon-website" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.07 0l2.12-2.12a5 5 0 0 0-7.07-7.07L11 4.93"/><path d="M14 11a5 5 0 0 0-7.07 0L4.81 13.12a5 5 0 0 0 7.07 7.07L13 19.07"/></svg>'),
  'x' => new \Illuminate\Support\HtmlString('<span data-ui="profile-social-icon-x" class="text-sm font-black leading-none">X</span>'),
  'instagram' => new \Illuminate\Support\HtmlString('<svg data-ui="profile-social-icon-instagram" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect width="16" height="16" x="4" y="4" rx="4"/><circle cx="12" cy="12" r="3.2"/><path d="M17.5 6.8h.01"/></svg>'),
  'facebook' => new \Illuminate\Support\HtmlString('<span data-ui="profile-social-icon-facebook" class="font-display text-base font-black leading-none">f</span>'),
  'youtube' => new \Illuminate\Support\HtmlString('<svg data-ui="profile-social-icon-youtube" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M21.6 7.2a3 3 0 0 0-2.1-2.1C17.6 4.6 12 4.6 12 4.6s-5.6 0-7.5.5a3 3 0 0 0-2.1 2.1A31.7 31.7 0 0 0 2 12a31.7 31.7 0 0 0 .4 4.8 3 3 0 0 0 2.1 2.1c1.9.5 7.5.5 7.5.5s5.6 0 7.5-.5a3 3 0 0 0 2.1-2.1A31.7 31.7 0 0 0 22 12a31.7 31.7 0 0 0-.4-4.8ZM10 15.5v-7l6 3.5-6 3.5Z"/></svg>'),
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
 <p class="text-fur">3-30 chars. Letters, numbers, hyphens, and underscores.</p>
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
 <div
 id="profile_modal_avatar_field"
 class="flex min-w-0 flex-col gap-4 rounded-[var(--radius-card)] border border-whisker/40 bg-warm-white p-4"
 data-ui="profile-avatar-upload-panel"
 x-data="{
 previewUrl: @js($avatarPreviewUrl),
 currentPreviewUrl: @js($avatarPreviewUrl),
 errorMessage: @js((string) $errors->first('avatar')),
 uploading: false,
 progress: 0,
 dragOver: false,
 selected: @js($avatarTemporaryUrl !== null),
 allowedTypes: ['image/jpeg', 'image/png', 'image/webp'],
 maxBytes: 3145728,
 maxSizeLabel: '3MB',
 openPicker() {
 if (this.uploading) {
 return;
 }

 this.$refs.avatarInput.click();
 },
 handleInput(event) {
 const file = event.target.files?.[0];

 if (file) {
 this.handleFile(file);
 }
 },
 handleDrop(event) {
 this.dragOver = false;
 const file = event.dataTransfer?.files?.[0];

 if (file) {
 this.handleFile(file);
 }
 },
 handleFile(file) {
 this.errorMessage = '';

 if (! this.allowedTypes.includes(file.type)) {
 this.clearSelection('Avatar must be a JPG, PNG, or WEBP image.');
 return;
 }

 if (file.size > this.maxBytes) {
 this.clearSelection(`Avatar must be smaller than ${this.maxSizeLabel}.`);
 return;
 }

 const reader = new FileReader();

 reader.onload = () => {
 this.previewUrl = reader.result;
 this.selected = true;
 this.progress = 0;
 this.$nextTick(() => this.startUpload(file));
 };

 reader.onerror = () => {
 this.clearSelection('We could not preview this image. Try another file.');
 };

 reader.readAsDataURL(file);
 },
 startUpload(file) {
 this.uploading = true;
 this.progress = 1;

 $wire.upload('avatar', file, () => {
 this.progress = 100;
 this.uploading = false;
 this.errorMessage = '';
 }, () => {
 this.clearSelection('Avatar upload failed. Try another image.');
 }, (event) => {
 this.progress = event.detail.progress;
 }, () => {
 this.clearSelection('Avatar upload was cancelled.');
 });
 },
 clearSelection(message) {
 this.uploading = false;
 this.progress = 0;
 this.selected = false;
 this.errorMessage = message;
 this.previewUrl = this.currentPreviewUrl;
 this.$refs.avatarInput.value = '';
 $wire.$set('avatar', null, false);
 },
 }"
 >
 <div class="flex items-start justify-between gap-3">
 <div class="min-w-0">
 <h4 class="text-sm font-bold text-bark">Avatar</h4>
 <p class="mt-1 text-xs leading-5 text-fur">Shown beside posts, comments, profile lists, and messages.</p>
 </div>
 <span class="ui-token shrink-0">Square</span>
 </div>

 <div class="flex flex-col items-center gap-3 text-center">
 <input
 x-ref="avatarInput"
 id="profile_modal_avatar"
 name="avatar"
 type="file"
 accept="image/jpeg,image/png,image/webp"
 class="sr-only"
 x-on:change="handleInput($event)"
 aria-describedby="profile_modal_avatar_help profile_modal_avatar_error"
 >
 <div
 role="button"
 tabindex="0"
 class="group relative h-32 w-32 cursor-pointer overflow-hidden rounded-pill border-4 border-warm-white bg-cream shadow-sm ring-1 ring-whisker/60 transition-all duration-150 hover:ring-2 hover:ring-paw focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-paw"
 data-ui="profile-avatar-drop-zone"
 :class="{ 'ring-2 ring-paw bg-paw-light/50': dragOver, 'cursor-wait opacity-90': uploading }"
 x-on:click="openPicker()"
 x-on:keydown.enter.prevent="openPicker()"
 x-on:keydown.space.prevent="openPicker()"
 x-on:dragover.prevent="if (! uploading) dragOver = true"
 x-on:dragleave.prevent="dragOver = false"
 x-on:drop.prevent="handleDrop($event)"
 :aria-busy="uploading.toString()"
 aria-label="Change profile avatar"
 >
 <img x-show="previewUrl" x-cloak :src="previewUrl || ''" alt="{{ $user->name }} avatar preview" class="h-full w-full object-cover" data-ui="profile-avatar-file-reader-preview">
 <div x-show="! previewUrl" class="{{ $user->profile_default_avatar_color }} flex h-full w-full items-center justify-center font-display text-4xl font-bold uppercase" aria-label="{{ $user->name }} avatar initial" role="img">
 {{ $user->profile_initial }}
 </div>
 <div
 class="absolute inset-x-0 bottom-0 bg-bark/70 px-3 py-2 text-xs font-bold text-warm-white opacity-0 transition-opacity duration-150 group-hover:opacity-100 group-focus:opacity-100"
 :class="{ 'opacity-100': dragOver }"
 data-ui="profile-avatar-change-photo-label"
 >
 Change photo
 </div>
 <div x-show="uploading" x-cloak class="absolute inset-0 flex items-center justify-center bg-bark/45" data-ui="profile-avatar-upload-progress">
 <div class="relative h-16 w-16 text-warm-white">
 <svg class="h-16 w-16 -rotate-90" viewBox="0 0 44 44" aria-hidden="true">
 <circle cx="22" cy="22" r="18" fill="none" stroke="currentColor" stroke-width="4" class="opacity-25"></circle>
 <circle cx="22" cy="22" r="18" fill="none" stroke="currentColor" stroke-width="4" pathLength="100" stroke-dasharray="100" :stroke-dashoffset="100 - progress" stroke-linecap="round"></circle>
 </svg>
 <span class="absolute inset-0 flex items-center justify-center text-xs font-bold" x-text="`${progress}%`"></span>
 </div>
 </div>
 </div>
 <p id="profile_modal_avatar_help" class="max-w-56 text-xs leading-5 text-fur">
 Click or drop a JPG, PNG, or WEBP image. Max 3MB.
 </p>
 <p id="profile_modal_avatar_error" x-show="errorMessage" x-cloak class="max-w-56 text-xs font-semibold leading-5 text-danger" role="alert" x-text="errorMessage"></p>
 <p x-show="selected && ! errorMessage && ! uploading" x-cloak class="text-xs font-semibold text-success" role="status">New avatar selected.</p>
 </div>
 @if ($user->avatar_url)
 <div class="rounded-[var(--radius-soft)] border border-whisker/40 bg-cream/35 p-3">
 <x-ui.checkbox id="profile_modal_remove_avatar" name="remove_avatar" label="Remove current avatar" wire:model="remove_avatar"/>
 </div>
 @endif
 </div>

 <div
 id="profile_modal_cover_field"
 class="flex min-w-0 flex-col gap-4 rounded-[var(--radius-card)] border border-whisker/40 bg-warm-white p-4"
 data-ui="profile-cover-upload-panel"
 x-data="{
 previewUrl: @js($coverPreviewUrl),
 currentPreviewUrl: @js($coverPreviewUrl),
 errorMessage: @js((string) $errors->first('cover')),
 uploading: false,
 progress: 0,
 dragOver: false,
 selected: @js($coverTemporaryUrl !== null),
 repositioning: @js($coverTemporaryUrl !== null),
 draggingCrop: false,
 position: @js((float) $cover_photo_position),
 currentPosition: @js((float) $cover_photo_position),
 dragStartY: 0,
 dragStartPosition: @js((float) $cover_photo_position),
 allowedTypes: ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
 maxBytes: 5242880,
 minWidth: 1200,
 minHeight: 400,
 openPicker() {
 if (this.uploading) {
 return;
 }

 this.$refs.coverInput.click();
 },
 handleInput(event) {
 const file = event.target.files?.[0];

 if (file) {
 this.handleFile(file);
 }
 },
 handleDrop(event) {
 this.dragOver = false;
 const file = event.dataTransfer?.files?.[0];

 if (file) {
 this.handleFile(file);
 }
 },
 handleFile(file) {
 this.errorMessage = '';

 if (! this.allowedTypes.includes(file.type)) {
 this.clearSelection('Cover must be a JPG, PNG, WEBP, or GIF image.');
 return;
 }

 if (file.size > this.maxBytes) {
 this.clearSelection('Cover must be smaller than 5MB.');
 return;
 }

 const dimensionsUrl = URL.createObjectURL(file);
 const image = new Image();

 image.onload = () => {
 URL.revokeObjectURL(dimensionsUrl);

 if (image.naturalWidth < this.minWidth || image.naturalHeight < this.minHeight) {
 this.clearSelection('Cover photo must be at least 1200 by 400 pixels.');
 return;
 }

 this.previewFile(file);
 };

 image.onerror = () => {
 URL.revokeObjectURL(dimensionsUrl);
 this.clearSelection('We could not read this cover image. Try another file.');
 };

 image.src = dimensionsUrl;
 },
 previewFile(file) {
 const reader = new FileReader();

 reader.onload = () => {
 this.previewUrl = reader.result;
 this.selected = true;
 this.repositioning = false;
 this.position = 50;
 this.updateCoverPosition(50);
 this.progress = 0;
 this.$nextTick(() => this.startUpload(file));
 };

 reader.onerror = () => {
 this.clearSelection('We could not preview this cover image. Try another file.');
 };

 reader.readAsDataURL(file);
 },
 startUpload(file) {
 this.uploading = true;
 this.progress = 1;

 $wire.upload('cover', file, () => {
 this.progress = 100;
 this.uploading = false;
 this.repositioning = true;
 this.errorMessage = '';
 }, () => {
 this.clearSelection('Cover upload failed. Try another image.');
 }, (event) => {
 this.progress = event.detail.progress;
 }, () => {
 this.clearSelection('Cover upload was cancelled.');
 });
 },
 startCropDrag(event) {
 if (! this.repositioning || ! this.previewUrl) {
 return;
 }

 this.draggingCrop = true;
 this.dragStartY = this.pointerY(event);
 this.dragStartPosition = this.position;
 },
 dragCrop(event) {
 if (! this.draggingCrop) {
 return;
 }

 event.preventDefault();
 const height = Math.max(this.$refs.coverDropZone.getBoundingClientRect().height, 1);
 const delta = ((this.pointerY(event) - this.dragStartY) / height) * 100;
 this.updateCoverPosition(this.dragStartPosition - delta);
 },
 stopCropDrag() {
 this.draggingCrop = false;
 },
 pointerY(event) {
 return event.touches?.[0]?.clientY ?? event.changedTouches?.[0]?.clientY ?? event.clientY;
 },
 updateCoverPosition(value) {
 this.position = Math.round(Math.min(100, Math.max(0, value)) * 100) / 100;
 $wire.$set('cover_photo_position', this.position, false);
 },
 clearSelection(message) {
 this.uploading = false;
 this.progress = 0;
 this.selected = false;
 this.repositioning = false;
 this.draggingCrop = false;
 this.errorMessage = message;
 this.previewUrl = this.currentPreviewUrl;
 this.updateCoverPosition(this.currentPosition);
 this.$refs.coverInput.value = '';
 $wire.$set('cover', null, false);
 },
 }"
 >
 <div class="flex items-start justify-between gap-3">
 <div class="min-w-0">
 <h4 class="text-sm font-bold text-bark">Cover Photo</h4>
 <p class="mt-1 text-xs leading-5 text-fur">This banner frames the top of your profile on desktop and mobile.</p>
 </div>
 <span class="ui-token shrink-0">Wide</span>
 </div>

 <div class="space-y-3">
 <input
 x-ref="coverInput"
 id="profile_modal_cover"
 name="cover"
 type="file"
 accept="image/jpeg,image/png,image/webp,image/gif"
 class="sr-only"
 x-on:change="handleInput($event)"
 aria-describedby="profile_modal_cover_help profile_modal_cover_error"
 >
 <input id="profile_modal_cover_position" type="hidden" name="cover_photo_position" :value="position" wire:model="cover_photo_position">
 <div
 x-ref="coverDropZone"
 role="button"
 tabindex="0"
 class="group relative aspect-3/1 w-full cursor-pointer overflow-hidden rounded-[var(--radius-soft)] border border-whisker/50 bg-cream shadow-sm ring-1 ring-transparent transition-all duration-150 hover:ring-2 hover:ring-paw focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw"
 data-ui="profile-cover-drop-zone"
 :class="{ 'ring-2 ring-paw bg-paw-light/50': dragOver, 'cursor-grab': repositioning && ! draggingCrop, 'cursor-grabbing': draggingCrop, 'cursor-wait opacity-90': uploading }"
 x-on:click="if (! repositioning) openPicker()"
 x-on:keydown.enter.prevent="if (! repositioning) openPicker()"
 x-on:keydown.space.prevent="if (! repositioning) openPicker()"
 x-on:dragover.prevent="if (! uploading) dragOver = true"
 x-on:dragleave.prevent="dragOver = false"
 x-on:drop.prevent="handleDrop($event)"
 x-on:mousedown.prevent="startCropDrag($event)"
 x-on:mousemove.window="dragCrop($event)"
 x-on:mouseup.window="stopCropDrag()"
 x-on:touchstart.passive="startCropDrag($event)"
 x-on:touchmove.window="dragCrop($event)"
 x-on:touchend.window="stopCropDrag()"
 :aria-busy="uploading.toString()"
 aria-label="Change cover photo"
 >
 <img x-show="previewUrl" x-cloak :src="previewUrl || ''" alt="{{ $user->name }} cover photo preview" class="h-full w-full select-none object-cover" data-ui="profile-cover-file-reader-preview" :style="`object-position: center ${position}%`" draggable="false">
 <div x-show="! previewUrl" class="{{ $user->profile_default_gradient }} h-full w-full" data-ui="profile-cover-gradient-preview"></div>
 <div
 x-show="! repositioning"
 class="absolute inset-x-0 bottom-0 bg-bark/55 px-3 py-2 text-xs font-semibold text-warm-white opacity-100 transition-opacity duration-150 group-hover:opacity-100"
 :class="{ 'opacity-100': dragOver }"
 data-ui="profile-cover-change-photo-label"
 >
 Click or drop cover photo
 </div>
 <div x-show="uploading" x-cloak class="absolute inset-0 flex items-center justify-center bg-bark/45" data-ui="profile-cover-upload-progress">
 <div class="rounded-[var(--radius-control)] bg-bark/70 px-3 py-2 text-xs font-bold text-warm-white">
 Uploading <span x-text="`${progress}%`"></span>
 </div>
 </div>
 </div>
 <div x-show="repositioning" x-cloak class="rounded-[var(--radius-soft)] border border-paw/25 bg-paw-light/35 p-3" data-ui="profile-cover-reposition-inline">
 <p class="text-xs font-semibold text-bark">Drag the image up or down to choose the best crop.</p>
 <div class="mt-2 flex items-center gap-3">
 <span class="text-xs text-fur">Top</span>
 <input
 type="range"
 min="0"
 max="100"
 step="0.01"
 x-model.number="position"
 x-on:input="updateCoverPosition(position)"
 class="h-2 w-full accent-paw"
 aria-label="Cover vertical focal point"
 >
 <span class="text-xs text-fur">Bottom</span>
 </div>
 </div>
 <p id="profile_modal_cover_help" class="text-xs leading-5 text-fur">
 JPG, PNG, WEBP, or GIF. Minimum 1200x400. Max 5MB.
 </p>
 <p id="profile_modal_cover_error" x-show="errorMessage" x-cloak class="text-xs font-semibold leading-5 text-danger" role="alert" x-text="errorMessage"></p>
 <p x-show="selected && ! errorMessage && ! uploading" x-cloak class="text-xs font-semibold text-success" role="status">New cover selected.</p>
 </div>
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
 <x-ui.input id="profile_modal_website" name="website" type="url" label="Website URL" :value="$website" placeholder="https://prus.dev" :prefix="$socialInputIcons['website']" :error="$errors->first('website')" wire:model.live.blur="website"/>
 <x-ui.input id="profile_modal_social_x" name="social_links[x]" type="text" label="Twitter/X username" :value="$social_links['x'] ?? null" placeholder="@username" :prefix="$socialInputIcons['x']" :error="$errors->first('social_links.x')" wire:model.live.blur="social_links.x"/>
 <x-ui.input id="profile_modal_social_instagram" name="social_links[instagram]" type="text" label="Instagram username" :value="$social_links['instagram'] ?? null" placeholder="@username" :prefix="$socialInputIcons['instagram']" :error="$errors->first('social_links.instagram')" wire:model.live.blur="social_links.instagram"/>
 <x-ui.input id="profile_modal_social_facebook" name="social_links[facebook]" type="url" label="Facebook profile URL" :value="$social_links['facebook'] ?? null" placeholder="https://facebook.com/username" :prefix="$socialInputIcons['facebook']" :error="$errors->first('social_links.facebook')" wire:model.live.blur="social_links.facebook"/>
 <x-ui.input id="profile_modal_social_youtube" name="social_links[youtube]" type="url" label="YouTube channel URL" :value="$social_links['youtube'] ?? null" placeholder="https://youtube.com/@username" :prefix="$socialInputIcons['youtube']" :error="$errors->first('social_links.youtube')" wire:model.live.blur="social_links.youtube"/>
 </div>
 </section>

 <section class="space-y-4 rounded-[var(--radius-card)] border border-whisker/40 bg-cream/25 p-4" data-ui="profile-edit-modal-section-privacy" aria-labelledby="profile-edit-privacy-title">
 <div>
 <h3 id="profile-edit-privacy-title" class="font-display text-base font-bold text-bark">Privacy</h3>
 <p class="mt-1 text-sm leading-6 text-fur">Update the most common privacy preferences immediately, without saving the full profile form.</p>
 </div>

 <div class="space-y-3" data-ui="profile-privacy-toggle-list">
 <div id="profile_modal_account_visibility" data-ui="profile-privacy-toggle-account-visibility" class="flex flex-col gap-3 rounded-[var(--radius-soft)] border border-whisker/40 bg-warm-white p-4 sm:flex-row sm:items-center sm:justify-between">
 <div class="min-w-0">
 <p class="text-sm font-bold text-bark">Account Visibility</p>
 <p class="mt-1 text-xs leading-5 text-fur">
 {{ $account_is_private ? 'Private profiles only reveal full content to accepted followers.' : 'Public profiles can be viewed by guests and members who are not blocked.' }}
 </p>
 </div>
 <div class="flex shrink-0 items-center gap-3">
 <span class="min-w-14 text-right text-xs font-bold uppercase {{ $account_is_private ? 'text-paw-dark' : 'text-fur' }}" aria-live="polite">
 {{ $account_is_private ? 'Private' : 'Public' }}
 </span>
 <button
 type="button"
 role="switch"
 aria-checked="{{ $account_is_private ? 'true' : 'false' }}"
 aria-label="Set account visibility to {{ $account_is_private ? 'public' : 'private' }}"
 wire:click="updateAccountVisibility({{ $account_is_private ? 'false' : 'true' }})"
 wire:loading.attr="disabled"
 wire:target="updateAccountVisibility"
 class="relative inline-flex h-8 w-16 shrink-0 items-center rounded-full border border-whisker/40 transition-colors duration-200 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw disabled:cursor-wait disabled:opacity-70 {{ $account_is_private ? 'bg-paw' : 'bg-cream' }}"
 >
 <span class="sr-only">Toggle account visibility</span>
 <span class="inline-block h-6 w-6 rounded-full bg-warm-white shadow-sm transition-transform duration-200 {{ $account_is_private ? 'translate-x-9' : 'translate-x-1' }}" aria-hidden="true"></span>
 </button>
 <span wire:loading wire:target="updateAccountVisibility" class="text-xs font-semibold text-fur" role="status">Saving...</span>
 </div>
 </div>

 <div id="profile_modal_privacy_display_birthdate" data-ui="profile-privacy-toggle-age" class="flex flex-col gap-3 rounded-[var(--radius-soft)] border border-whisker/40 bg-warm-white p-4 sm:flex-row sm:items-center sm:justify-between">
 <div class="min-w-0">
 <p class="text-sm font-bold text-bark">Show age on profile</p>
 <p class="mt-1 text-xs leading-5 text-fur">Only your calculated age is shown; your birth date is never displayed.</p>
 </div>
 <div class="flex shrink-0 items-center gap-3">
 <span class="min-w-10 text-right text-xs font-bold uppercase {{ $privacy_display_birthdate ? 'text-paw-dark' : 'text-fur' }}" aria-live="polite">
 {{ $privacy_display_birthdate ? 'On' : 'Off' }}
 </span>
 <button
 type="button"
 role="switch"
 aria-checked="{{ $privacy_display_birthdate ? 'true' : 'false' }}"
 aria-label="{{ $privacy_display_birthdate ? 'Hide age on profile' : 'Show age on profile' }}"
 wire:click="updateShowAge({{ $privacy_display_birthdate ? 'false' : 'true' }})"
 wire:loading.attr="disabled"
 wire:target="updateShowAge"
 class="relative inline-flex h-8 w-16 shrink-0 items-center rounded-full border border-whisker/40 transition-colors duration-200 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw disabled:cursor-wait disabled:opacity-70 {{ $privacy_display_birthdate ? 'bg-paw' : 'bg-cream' }}"
 >
 <span class="sr-only">Toggle age display</span>
 <span class="inline-block h-6 w-6 rounded-full bg-warm-white shadow-sm transition-transform duration-200 {{ $privacy_display_birthdate ? 'translate-x-9' : 'translate-x-1' }}" aria-hidden="true"></span>
 </button>
 <span wire:loading wire:target="updateShowAge" class="text-xs font-semibold text-fur" role="status">Saving...</span>
 </div>
 </div>

 <div id="profile_modal_privacy_display_email" data-ui="profile-privacy-toggle-email" class="flex flex-col gap-3 rounded-[var(--radius-soft)] border border-whisker/40 bg-warm-white p-4 sm:flex-row sm:items-center sm:justify-between">
 <div class="min-w-0">
 <p class="text-sm font-bold text-bark">Allow people to find me by email address</p>
 <p class="mt-1 text-xs leading-5 text-fur">Lets signed-in members discover your profile when they already know your email address.</p>
 </div>
 <div class="flex shrink-0 items-center gap-3">
 <span class="min-w-10 text-right text-xs font-bold uppercase {{ $privacy_display_email ? 'text-paw-dark' : 'text-fur' }}" aria-live="polite">
 {{ $privacy_display_email ? 'On' : 'Off' }}
 </span>
 <button
 type="button"
 role="switch"
 aria-checked="{{ $privacy_display_email ? 'true' : 'false' }}"
 aria-label="{{ $privacy_display_email ? 'Disable email discovery' : 'Enable email discovery' }}"
 wire:click="updateEmailDiscovery({{ $privacy_display_email ? 'false' : 'true' }})"
 wire:loading.attr="disabled"
 wire:target="updateEmailDiscovery"
 class="relative inline-flex h-8 w-16 shrink-0 items-center rounded-full border border-whisker/40 transition-colors duration-200 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw disabled:cursor-wait disabled:opacity-70 {{ $privacy_display_email ? 'bg-paw' : 'bg-cream' }}"
 >
 <span class="sr-only">Toggle email discovery</span>
 <span class="inline-block h-6 w-6 rounded-full bg-warm-white shadow-sm transition-transform duration-200 {{ $privacy_display_email ? 'translate-x-9' : 'translate-x-1' }}" aria-hidden="true"></span>
 </button>
 <span wire:loading wire:target="updateEmailDiscovery" class="text-xs font-semibold text-fur" role="status">Saving...</span>
 </div>
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
