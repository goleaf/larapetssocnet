<?php

namespace App\Services;

use App\Enums\GroupMemberRole;
use App\Enums\GroupMemberStatus;
use App\Models\Groups\Group;
use App\Models\Groups\GroupBan;
use App\Models\Groups\GroupMember;
use App\Models\Identity\User;

class GroupVisibilityService
{
    public function canViewGroup(?User $viewer, Group $group): bool
    {
        $privacy = $group->normalizedPrivacy();

        if ($viewer && $this->isBanned($viewer, $group)) {
            return false;
        }

        if ($privacy === 'public') {
            return ! $this->isBlockedBetween($viewer, $group);
        }

        if (! $viewer instanceof User) {
            return false;
        }

        if ($this->isBlockedBetween($viewer, $group)) {
            return false;
        }

        if ($privacy === 'private') {
            return true;
        }

        if ($this->isOwner($viewer, $group)) {
            return true;
        }

        if ($this->isPrivilegedModerator($viewer)) {
            return true;
        }

        return $this->isActiveMember($viewer, $group);
    }

    public function canViewGroupPosts(?User $viewer, Group $group): bool
    {
        if (! $this->canViewGroup($viewer, $group)) {
            return false;
        }

        $privacy = $group->normalizedPrivacy();

        if ($privacy === 'public') {
            return true;
        }

        if (! $viewer instanceof User) {
            return false;
        }

        return $this->isOwner($viewer, $group) || $this->isActiveMember($viewer, $group) || $this->isPrivilegedModerator($viewer);
    }

    public function canViewGroupMembers(?User $viewer, Group $group): bool
    {
        if (! $this->canViewGroup($viewer, $group)) {
            return false;
        }

        $privacy = $group->normalizedPrivacy();

        if ($privacy === 'public') {
            return true;
        }

        if (! $viewer instanceof User) {
            return false;
        }

        return $this->isOwner($viewer, $group) || $this->isActiveMember($viewer, $group) || $this->isPrivilegedModerator($viewer);
    }

    public function canJoinGroup(User $viewer, Group $group): bool
    {
        if ($group->isArchived()) {
            return false;
        }

        if ($this->isBlockedBetween($viewer, $group)) {
            return false;
        }

        if ($this->isBanned($viewer, $group)) {
            return false;
        }

        if ($group->normalizedPrivacy() === 'secret') {
            return false;
        }

        $membership = $group->membershipForUserId((int) $viewer->getKey());

        return ! ($membership && $group->isActiveMembership($membership));
    }

    public function canManageGroup(User $viewer, Group $group): bool
    {
        if ($this->isOwner($viewer, $group)) {
            return true;
        }

        $membership = $group->membershipForUserId((int) $viewer->getKey());

        return $group->isActiveMembership($membership)
            && in_array((string) ($membership?->role?->value ?? ''), GroupMemberRole::managerValues(), true);
    }

    public function isOwner(User $viewer, Group $group): bool
    {
        return $group->isOwner($viewer);
    }

    public function isActiveMember(User $viewer, Group $group): bool
    {
        $membership = $group->membershipForUserId((int) $viewer->getKey());

        return $group->isActiveMembership($membership);
    }

    private function isBanned(User $viewer, Group $group): bool
    {
        $bannedMembership = GroupMember::query()
            ->forGroup((int) $group->getKey())
            ->forUser((int) $viewer->getKey())
            ->where('status', GroupMemberStatus::Banned->value)
            ->exists();

        if ($bannedMembership) {
            return true;
        }

        return GroupBan::query()
            ->where('group_id', $group->getKey())
            ->where('user_id', $viewer->getKey())
            ->exists();
    }

    private function isBlockedBetween(?User $viewer, Group $group): bool
    {
        if (! $viewer instanceof User) {
            return false;
        }

        $owner = $group->owner ?? $group->owner()->first();

        if (! $owner) {
            return false;
        }

        return $viewer->hasBlockingRelationshipWith($owner);
    }

    private function isPrivilegedModerator(User $viewer): bool
    {
        return $viewer->hasAnyRole(['admin', 'moderator']);
    }
}
