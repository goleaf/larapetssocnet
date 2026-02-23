<?php

namespace App\Services;

use App\Models\Group;
use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class SettingsService
{
    public function updateProfile(User $user, array $data, ?string $usernameConfirm = null): User
    {
        if (isset($data['username']) && $data['username'] !== $user->username) {
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
        $user->update([
            'profile_visibility' => $settings['profile_visibility'] ?? $user->profile_visibility,
            'messaging_permission' => $settings['messaging_permission'] ?? $user->messaging_permission,
            'pets_visibility' => $settings['pets_visibility'] ?? $user->pets_visibility,
            'groups_visibility' => $settings['groups_visibility'] ?? $user->groups_visibility,
            'show_in_explore' => $settings['show_in_explore'] ?? $user->show_in_explore,
            'open_following' => $settings['open_following'] ?? $user->open_following,
        ]);

        return $user;
    }

    public function saveNotificationPreferences(User $user, array $preferences): User
    {
        $user->update([
            'notification_preferences' => collect($preferences)
                ->mapWithKeys(fn ($value, $key) => [$key => (bool) $value])
                ->toArray(),
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
