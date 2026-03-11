<?php

namespace App\Policies;

use App\Models\Pet;
use App\Models\User;

class PetPolicy
{
    public function view(?User $user, Pet $pet): bool
    {
        if ($pet->is_public) {
            return true;
        }

        if (! $user) {
            return false;
        }

        return (int) $pet->user_id === (int) $user->getKey() || $user->hasAnyRole(['admin', 'moderator']);
    }

    public function create(User $user): bool
    {
        return ! empty($user->getKey());
    }

    public function update(User $user, Pet $pet): bool
    {
        return (int) $pet->user_id === (int) $user->getKey() || $user->hasAnyRole(['admin', 'moderator']);
    }

    public function delete(User $user, Pet $pet): bool
    {
        return $this->update($user, $pet);
    }

    public function restore(User $user, Pet $pet): bool
    {
        return $this->update($user, $pet);
    }
}
