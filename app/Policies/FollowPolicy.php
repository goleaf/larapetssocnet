<?php

namespace App\Policies;

use App\Models\User;

class FollowPolicy
{
    public function follow(?User $auth, User $target): bool
    {
        return $target->canBeFollowedBy($auth);
    }

    public function unfollow(?User $auth, User $target): bool
    {
        return $auth instanceof User && ! $auth->is($target);
    }

    public function viewFollowers(?User $viewer, User $user): bool
    {
        return $user->canViewFollowersList($viewer);
    }

    public function viewFollowing(?User $viewer, User $user): bool
    {
        return $user->canViewFollowingList($viewer);
    }

    public function manageRequests(?User $auth, User $owner): bool
    {
        if (! $auth) {
            return false;
        }

        if ((bool) $auth->is_banned) {
            return false;
        }

        return $auth->is($owner);
    }

    public function removeFollower(?User $auth, User $follower): bool
    {
        if (! $auth) {
            return false;
        }

        return $auth->canRemoveFollower($follower);
    }
}
