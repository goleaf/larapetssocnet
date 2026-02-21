<?php

namespace App\Policies;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;

class GroupPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(?User $user, Group $group): bool
    {
        $privacy = $this->privacy($group);

        if ($privacy === 'public') {
            return true;
        }

        if (! $user) {
            return false;
        }

        if ($this->isOwner($user, $group)) {
            return true;
        }

        return $this->isActiveMembership($this->membership($user, $group));
    }

    public function create(User $user): bool
    {
        return ! empty($user->getKey());
    }

    public function update(User $user, Group $group): bool
    {
        if ($this->isOwner($user, $group)) {
            return true;
        }

        $membership = $this->membership($user, $group);

        return $this->isActiveMembership($membership)
            && in_array((string) $membership?->role, ['owner', 'admin'], true);
    }

    public function delete(User $user, Group $group): bool
    {
        return $this->isOwner($user, $group);
    }

    public function post(User $user, Group $group): bool
    {
        if ($this->isOwner($user, $group)) {
            return true;
        }

        return $this->isActiveMembership($this->membership($user, $group));
    }

    public function manageMembers(User $user, Group $group): bool
    {
        if ($this->isOwner($user, $group)) {
            return true;
        }

        $membership = $this->membership($user, $group);

        return $this->isActiveMembership($membership)
            && in_array((string) $membership?->role, ['owner', 'admin'], true);
    }

    public function moderate(User $user, Group $group): bool
    {
        if ($this->isOwner($user, $group)) {
            return true;
        }

        $membership = $this->membership($user, $group);

        return $this->isActiveMembership($membership)
            && in_array((string) $membership?->role, ['owner', 'admin', 'moderator'], true);
    }

    private function privacy(Group $group): string
    {
        $privacy = strtolower((string) ($group->privacy ?: $group->type ?: 'public'));

        return in_array($privacy, ['public', 'private', 'secret'], true)
            ? $privacy
            : 'public';
    }

    private function isOwner(User $user, Group $group): bool
    {
        return (int) $group->owner_user_id === (int) $user->getKey();
    }

    private function membership(User $user, Group $group): ?GroupMember
    {
        return $group->memberships()
            ->where('user_id', $user->getKey())
            ->first();
    }

    private function isActiveMembership(?GroupMember $membership): bool
    {
        if (! $membership) {
            return false;
        }

        return $membership->status === null
            || in_array((string) $membership->status, ['active', 'accepted'], true);
    }
}
