<?php

namespace App\Policies;

use App\Models\Group;
use App\Models\User;

class GroupPolicy
{
    public function view(?User $user, Group $group): bool
    {
        $privacy = (string) ($group->type ?? $group->privacy ?? 'public');

        if ($privacy === 'public') {
            return true;
        }

        if (! $user) {
            return false;
        }

        if ((int) $group->owner_user_id === (int) $user->getKey()) {
            return true;
        }

        return $group->memberships()
            ->where('user_id', $user->getKey())
            ->whereIn('status', ['active', 'accepted'])
            ->exists();
    }

    public function create(User $user): bool
    {
        return ! empty($user->getKey());
    }

    public function update(User $user, Group $group): bool
    {
        if ((int) $group->owner_user_id === (int) $user->getKey()) {
            return true;
        }

        return $user->hasAnyRole(['admin', 'moderator']);
    }

    public function delete(User $user, Group $group): bool
    {
        return $this->update($user, $group);
    }

    public function join(User $user, Group $group): bool
    {
        if ((string) ($group->type ?? $group->privacy ?? 'public') === 'secret') {
            return false;
        }

        return ! $group->memberships()
            ->where('user_id', $user->getKey())
            ->whereIn('status', ['active', 'accepted', 'pending', 'banned'])
            ->exists();
    }

    public function moderate(User $user, Group $group): bool
    {
        if ((int) $group->owner_user_id === (int) $user->getKey()) {
            return true;
        }

        return $group->memberships()
            ->where('user_id', $user->getKey())
            ->whereIn('role', ['owner', 'admin', 'moderator'])
            ->whereIn('status', ['active', 'accepted'])
            ->exists();
    }
}
