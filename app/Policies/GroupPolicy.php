<?php

namespace App\Policies;

use App\Enums\GroupMemberRole;
use App\Enums\GroupMemberStatus;
use App\Models\Group;
use App\Models\GroupBan;
use App\Models\GroupMember;
use App\Models\User;
use App\Services\GroupVisibilityService;

class GroupPolicy
{
    public function __construct(private readonly GroupVisibilityService $visibility) {}

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(?User $user, Group $group): bool
    {
        return $this->visibility->canViewGroup($user, $group);
    }

    public function create(User $user): bool
    {
        return ! empty($user->getKey());
    }

    public function update(User $user, Group $group): bool
    {
        if ($this->visibility->isOwner($user, $group)) {
            return true;
        }

        $membership = $this->membership($user, $group);

        return $this->isActiveMembership($membership)
            && in_array((string) ($membership?->role?->value ?? ''), [
                GroupMemberRole::Owner->value,
                GroupMemberRole::Admin->value,
            ], true);
    }

    public function delete(User $user, Group $group): bool
    {
        return $this->visibility->isOwner($user, $group);
    }

    public function post(User $user, Group $group): bool
    {
        if ($this->visibility->isOwner($user, $group)) {
            return true;
        }

        return $this->isActiveMembership($this->membership($user, $group));
    }

    public function viewMembers(User $user, Group $group): bool
    {
        return $this->visibility->canViewGroupMembers($user, $group);
    }

    public function manageMembers(User $user, Group $group): bool
    {
        if ($this->visibility->isOwner($user, $group)) {
            return true;
        }

        $membership = $this->membership($user, $group);

        return $this->isActiveMembership($membership)
            && in_array((string) ($membership?->role?->value ?? ''), [
                GroupMemberRole::Owner->value,
                GroupMemberRole::Admin->value,
            ], true);
    }

    public function moderate(User $user, Group $group): bool
    {
        if ($this->visibility->isOwner($user, $group)) {
            return true;
        }

        $membership = $this->membership($user, $group);

        return $this->isActiveMembership($membership)
            && in_array((string) ($membership?->role?->value ?? ''), [
                GroupMemberRole::Owner->value,
                GroupMemberRole::Admin->value,
                GroupMemberRole::Moderator->value,
            ], true);
    }

    public function manageCover(User $user, Group $group): bool
    {
        return $this->update($user, $group);
    }

    public function join(User $user, Group $group): bool
    {
        if ($group->normalizedPrivacy() === 'secret') {
            return false;
        }

        if (GroupBan::query()
            ->where('group_id', $group->getKey())
            ->where('user_id', $user->getKey())
            ->exists()) {
            return false;
        }

        if ($this->isActiveMembership($this->membership($user, $group))) {
            return false;
        }

        return true;
    }

    public function requestJoin(User $user, Group $group): bool
    {
        return $this->join($user, $group);
    }

    public function leave(User $user, Group $group): bool
    {
        if ($this->visibility->isOwner($user, $group)) {
            return false;
        }

        $membership = $this->membership($user, $group);

        return $this->isActiveMembership($membership)
            || $this->hasPendingMembership($membership);
    }

    public function approveJoin(User $user, Group $group): bool
    {
        return $this->manageMembers($user, $group);
    }

    public function rejectJoin(User $user, Group $group): bool
    {
        return $this->manageMembers($user, $group);
    }

    public function updateMemberRole(User $user, Group $group): bool
    {
        return $this->manageMembers($user, $group);
    }

    public function removeMember(User $user, Group $group): bool
    {
        return $this->manageMembers($user, $group);
    }

    public function banMember(User $user, Group $group): bool
    {
        return $this->moderate($user, $group);
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
            || in_array((string) ($membership->status?->value ?? ''), GroupMemberStatus::activeValues(), true);
    }

    private function hasPendingMembership(?GroupMember $membership): bool
    {
        if (! $membership) {
            return false;
        }

        return (string) ($membership->status?->value ?? '') === GroupMemberStatus::Pending->value;
    }
}
