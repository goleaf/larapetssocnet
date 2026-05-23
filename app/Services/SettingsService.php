<?php

namespace App\Services;

use App\Enums\ProfileVisibility;
use App\Models\Groups\Group;
use App\Models\Identity\User;
use App\Support\Usernames\UsernameNormalizer;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Fluent;
use Illuminate\Validation\ValidationException;

class SettingsService
{
    public function updateProfile(User $user, array $data, ?string $usernameConfirm = null): User
    {
        $currentUsername = UsernameNormalizer::normalize((string) $user->username);
        $incomingUsername = isset($data['username']) ? UsernameNormalizer::normalize((string) $data['username']) : null;

        if ($incomingUsername !== null && $incomingUsername !== $currentUsername) {
            if ($usernameConfirm !== $user->username) {
                throw ValidationException::withMessages([
                    'username_confirm' => 'The confirmation username does not match your current username.',
                ]);
            }
            $data['username_changed_at'] = now();
        }

        $user->update($data);

        return $user;
    }

    public function changePassword(User $user, string $currentPassword, string $newPassword): User
    {
        if (! Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'The provided password does not match your current password.',
            ]);
        }

        $user->update([
            'password' => Hash::make($newPassword),
            'password_changed_at' => now(),
        ]);

        return $user;
    }

    public function savePrivacySettings(User $user, array $settings): User
    {
        $settingsPayload = new Fluent($settings);

        if ($settingsPayload->isEmpty()) {
            return $user;
        }

        $privacySettings = new Fluent($settingsPayload->only([
            'profile_visibility',
            'messaging_permission',
            'pets_visibility',
            'groups_visibility',
            'show_in_explore',
            'open_following',
            'privacy_display_last_seen',
        ]));

        if ($privacySettings->isNotEmpty()) {
            $normalizedSettings = $privacySettings->toArray();
            $profileVisibility = null;

            foreach (['show_in_explore', 'open_following', 'privacy_display_last_seen'] as $booleanSetting) {
                if (array_key_exists($booleanSetting, $normalizedSettings)) {
                    $normalizedSettings[$booleanSetting] = (bool) $normalizedSettings[$booleanSetting];
                }
            }

            if (array_key_exists('profile_visibility', $normalizedSettings)) {
                $profileVisibility = ProfileVisibility::fromValue((string) $normalizedSettings['profile_visibility']);
            }

            if (array_key_exists('profile_visibility', $normalizedSettings)
                && $normalizedSettings['profile_visibility'] === 'private') {
                $normalizedSettings['show_in_explore'] = false;
                $normalizedSettings['open_following'] = false;
            }

            $user->update($normalizedSettings);

            if (array_key_exists('profile_visibility', $normalizedSettings)) {
                app(ProfileVisibilityService::class)->syncLegacyPrivacy(
                    $user,
                    $profileVisibility ?? $user->profileVisibility()
                );
            }
        }

        return $user;
    }

    public function saveNotificationPreferences(User $user, array $preferences): User
    {
        $preferencesPayload = new Fluent($preferences);

        if ($preferencesPayload->isEmpty()) {
            $user->update(['notification_preferences' => []]);

            return $user;
        }

        $user->update([
            'notification_preferences' => collect($preferencesPayload->all())
                ->mapWithKeys(fn ($value, $key): array => [(string) $key => (bool) $value])
                ->all(),
        ]);

        return $user;
    }

    public function initiateDeletion(User $user, ?string $reason = null): User
    {
        $user->update([
            'scheduled_deletion_at' => now()->addDays(30),
            'deletion_reason' => $reason,
        ]);

        $this->handleGroupOwnershipTransfers($user);

        return $user;
    }

    protected function handleGroupOwnershipTransfers(User $user): void
    {
        $ownedGroups = Group::where('owner_id', $user->id)->get();

        foreach ($ownedGroups as $group) {
            $oldestAdmin = $group->members()
                ->wherePivot('role', 'admin')
                ->where('users.id', '!=', $user->id)
                ->orderBy('group_user.created_at', 'asc')
                ->first();

            if ($oldestAdmin) {
                $group->update(['owner_id' => $oldestAdmin->id]);
            } else {
                $group->delete();
            }
        }
    }

    public function cancelDeletion(User $user): User
    {
        $user->update([
            'scheduled_deletion_at' => null,
            'deletion_reason' => null,
        ]);

        return $user;
    }
}
