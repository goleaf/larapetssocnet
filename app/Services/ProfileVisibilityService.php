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
        if ($owner->isUnavailableForProfile()) {
            return false;
        }

        if ($viewer instanceof User && $viewer->isUnavailableForProfile()) {
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
        if ($owner->isUnavailableForProfile()) {
            return false;
        }

        if ($viewer instanceof User && $viewer->isUnavailableForProfile()) {
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
        if ($owner->isUnavailableForProfile()) {
            return false;
        }

        if ($viewer instanceof User && $viewer->isUnavailableForProfile()) {
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
        if ($owner->isUnavailableForProfile()) {
            return false;
        }

        if ($viewer instanceof User && $viewer->isUnavailableForProfile()) {
            return false;
        }

        if ($viewer && $viewer->hasBlockingRelationshipWith($owner)) {
            return false;
        }

        if ($viewer && ($viewer->is($owner) || $viewer->hasAnyRole(['admin', 'moderator']))) {
            return true;
        }

        if ((bool) $owner->is_private) {
            return $viewer instanceof User && $viewer->isFollowing($owner);
        }

        return match ($this->resolve($owner)) {
            ProfileVisibility::Public => true,
            ProfileVisibility::FollowersOnly => $viewer && $viewer->isFollowing($owner),
            ProfileVisibility::Private => false,
        };
    }

    public function canViewFollowing(?User $viewer, User $owner): bool
    {
        if ($owner->isUnavailableForProfile()) {
            return false;
        }

        if ($viewer instanceof User && $viewer->isUnavailableForProfile()) {
            return false;
        }

        if ($viewer && $viewer->hasBlockingRelationshipWith($owner)) {
            return false;
        }

        if ($viewer && ($viewer->is($owner) || $viewer->hasAnyRole(['admin', 'moderator']))) {
            return true;
        }

        if ((bool) $owner->is_private) {
            return false;
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

    public function canViewLocation(?User $viewer, User $owner): bool
    {
        if (! $this->canViewFullProfile($viewer, $owner)) {
            return false;
        }

        return $viewer?->is($owner) === true
            || $viewer?->hasAnyRole(['admin', 'moderator']) === true
            || (bool) $owner->privacy_display_location;
    }

    public function canMessage(?User $viewer, User $owner): bool
    {
        if (! $viewer instanceof User || $viewer->is($owner)) {
            return false;
        }

        if ($owner->isUnavailableForProfile() || $viewer->isUnavailableForProfile()) {
            return false;
        }

        if ($viewer->hasBlockingRelationshipWith($owner)) {
            return false;
        }

        if (! $this->canViewProfileShell($viewer, $owner)) {
            return false;
        }

        if ($viewer->hasAnyRole(['admin', 'moderator'])) {
            return true;
        }

        return $viewer->isFollowing($owner) && $owner->isFollowing($viewer);
    }

    public function syncLegacyPrivacy(User $user, ProfileVisibility $visibility): void
    {
        $user->forceFill([
            'profile_visibility' => $visibility->value,
            'is_private' => $visibility->marksAccountPrivate(),
        ])->save();
    }
}
