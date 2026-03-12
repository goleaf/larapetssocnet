<?php

namespace App\Policies;

use App\Models\User;

class BlockPolicy
{
    public function block(User $auth, User $target): bool
    {
        if ($auth->is($target)) {
            return false;
        }

        return ! $target->hasAnyRole(['admin', 'moderator']);
    }

    public function unblock(User $auth, User $target): bool
    {
        return ! $auth->is($target);
    }
}
