<?php

namespace App\Policies;

use App\Models\User;

class FollowPolicy
{
    public function follow(?User $auth, User $target): bool
    {
        if (! $auth) {
            return false;
        }

        if ($auth->is($target) || (bool) $target->is_banned) {
            return false;
        }

        return ! $auth->hasBlocked($target) && ! $target->hasBlocked($auth);
    }

    public function unfollow(?User $auth, User $target): bool
    {
        return $auth instanceof User && ! $auth->is($target);
    }

    public function viewFollowers(?User $viewer, User $user): bool
    {
        if ((bool) $user->is_banned) {
            return false;
        }

        if (! $viewer) {
            return ! (bool) $user->is_private;
        }

        if ($viewer->is($user) || $viewer->hasAnyRole(['admin', 'moderator'])) {
            return true;
        }

        if ((bool) $user->is_private) {
            return $viewer->isFollowing($user);
        }

        return true;
    }

    public function viewFollowing(?User $viewer, User $user): bool
    {
        return $this->viewFollowers($viewer, $user);
    }
}
