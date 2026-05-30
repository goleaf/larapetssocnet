<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Identity\User;

class UserPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $actor, User $target): bool
    {
        return $target->canBeViewedBy($actor);
    }

    public function viewFollowers(?User $actor, User $target): bool
    {
        return $target->canViewFollowersList($actor);
    }

    public function viewFollowing(?User $actor, User $target): bool
    {
        return $target->canViewFollowingList($actor);
    }

    public function update(User $actor, User $target): bool
    {
        return $actor->is($target) || $actor->hasAnyRole(['admin', 'moderator']);
    }

    public function editProfile(User $actor, User $target): bool
    {
        return $actor->is($target);
    }

    public function updateProfile(User $actor, User $target): bool
    {
        return $this->editProfile($actor, $target);
    }

    public function repositionProfileCover(User $actor, User $target): bool
    {
        return $this->editProfile($actor, $target);
    }

    public function updateAvatar(User $actor, User $target): bool
    {
        return $this->update($actor, $target);
    }

    public function updateCover(User $actor, User $target): bool
    {
        return $this->update($actor, $target);
    }

    public function follow(User $actor, User $target): bool
    {
        if ($actor->is($target)) {
            return false;
        }

        return ! $actor->hasBlockingRelationshipWith($target);
    }

    public function unfollow(User $actor, User $target): bool
    {
        return ! $actor->is($target);
    }

    public function block(User $actor, User $target): bool
    {
        if ($actor->is($target)) {
            return false;
        }

        return ! $target->hasAnyRole(['admin', 'moderator']);
    }

    public function unblock(User $actor, User $target): bool
    {
        return ! $actor->is($target);
    }

    public function report(User $actor, User $target): bool
    {
        if ($actor->is($target)) {
            return false;
        }

        if ($actor->hasBlockingRelationshipWith($target)) {
            return false;
        }

        return $target->canBeViewedBy($actor);
    }
}
