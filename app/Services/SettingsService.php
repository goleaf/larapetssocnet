<?php

namespace App\Services;

use App\Models\Group;
use App\Models\User;
use App\Models\UserBlock;
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
        ]));

        if ($privacySettings->isNotEmpty()) {
            $normalizedSettings = $privacySettings->toArray();

            foreach (['show_in_explore', 'open_following'] as $booleanSetting) {
                if (array_key_exists($booleanSetting, $normalizedSettings)) {
                    $normalizedSettings[$booleanSetting] = (bool) $normalizedSettings[$booleanSetting];
                }
            }

            $user->update($normalizedSettings);
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
                ->mapWithKeys(fn ($value, $key) => [(string) $key => (bool) $value])
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

    public function blockUser(User $blocker, User $blocked): void
    {
        UserBlock::firstOrCreate([
            'blocker_id' => $blocker->id,
            'blocked_id' => $blocked->id,
        ]);

        if (method_exists($blocker, 'unfollow') && $blocker->isFollowing($blocked)) {
            $blocker->unfollow($blocked);
        }
        if (method_exists($blocked, 'unfollow') && $blocked->isFollowing($blocker)) {
            $blocked->unfollow($blocker);
        }
    }

    public function unblockUser(User $blocker, User $blocked): void
    {
        UserBlock::where('blocker_id', $blocker->id)
            ->where('blocked_id', $blocked->id)
            ->delete();
    }
}
