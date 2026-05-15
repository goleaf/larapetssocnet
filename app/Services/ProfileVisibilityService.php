<?php

namespace App\Services;

use App\Enums\ProfileVisibility;
use App\Models\Identity\User;

class ProfileVisibilityService
{
    public function resolve(User $owner): ProfileVisibility
    {
        $resolved = ProfileVisibility::fromValue($owner->profile_visibility);

        if ($resolved instanceof ProfileVisibility) {
            if ($resolved === ProfileVisibility::Public && (bool) $owner->is_private) {
                return ProfileVisibility::FollowersOnly;
            }

            return $resolved;
        }

        return (bool) $owner->is_private
            ? ProfileVisibility::FollowersOnly
            : ProfileVisibility::Public;
    }

    public function canDiscoverProfile(?User $viewer, User $owner): bool
    {
        if ((bool) $owner->is_banned) {
            return false;
        }

        if ($viewer && $viewer->hasBlockingRelationshipWith($owner)) {
            return false;
        }

        if ($viewer && ($viewer->is($owner) || $viewer->hasAnyRole(['admin', 'moderator']))) {
            return true;
        }

        if (! (bool) $owner->show_in_explore) {
            return false;
        }

        return $this->resolve($owner) === ProfileVisibility::Public;
    }

    public function canViewProfileShell(?User $viewer, User $owner): bool
    {
        if ((bool) $owner->is_banned) {
            return false;
        }

        if ($viewer && $viewer->hasBlockingRelationshipWith($owner)) {
            return false;
        }

        if ($viewer && ($viewer->is($owner) || $viewer->hasAnyRole(['admin', 'moderator']))) {
            return true;
        }

        return $this->resolve($owner) !== ProfileVisibility::Private;
    }

    public function canViewFullProfile(?User $viewer, User $owner): bool
    {
        if ((bool) $owner->is_banned) {
            return false;
        }

        if ($viewer && $viewer->hasBlockingRelationshipWith($owner)) {
            return false;
        }

        if ($viewer && ($viewer->is($owner) || $viewer->hasAnyRole(['admin', 'moderator']))) {
            return true;
        }

        return match ($this->resolve($owner)) {
            ProfileVisibility::Public => true,
            ProfileVisibility::FollowersOnly => $viewer && $viewer->isFollowing($owner),
            ProfileVisibility::Private => false,
        };
    }

    public function canViewProfilePosts(?User $viewer, User $owner): bool
    {
        return $this->canViewFullProfile($viewer, $owner);
    }

    public function canViewFollowers(?User $viewer, User $owner): bool
    {
        if ((bool) $owner->is_banned) {
            return false;
        }

        if ($viewer && $viewer->hasBlockingRelationshipWith($owner)) {
            return false;
        }

        if ($viewer && ($viewer->is($owner) || $viewer->hasAnyRole(['admin', 'moderator']))) {
            return true;
        }

        return match ($this->resolve($owner)) {
            ProfileVisibility::Public => true,
            ProfileVisibility::FollowersOnly => $viewer && $viewer->isFollowing($owner),
            ProfileVisibility::Private => false,
        };
    }

    public function canViewFollowing(?User $viewer, User $owner): bool
    {
        if ((bool) $owner->is_banned) {
            return false;
        }

        if ($viewer && $viewer->hasBlockingRelationshipWith($owner)) {
            return false;
        }

        if ($viewer && ($viewer->is($owner) || $viewer->hasAnyRole(['admin', 'moderator']))) {
            return true;
        }

        $visibility = $this->resolve($owner);

        if ($visibility === ProfileVisibility::Private) {
            return false;
        }

        if ((bool) $owner->open_following) {
            return $visibility !== ProfileVisibility::Private;
        }

        return $viewer && $viewer->isFollowing($owner);
    }

    public function syncLegacyPrivacy(User $user, ProfileVisibility $visibility): void
    {
        $user->forceFill([
            'profile_visibility' => $visibility->value,
            'is_private' => $visibility->marksAccountPrivate(),
        ])->save();
    }
}
