<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Identity\User;
use App\Models\Pets\Pet;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PetFollowService
{
    public function follow(User $user, Pet $pet): bool
    {
        if ($user->id === $pet->user_id) {
            throw new RuntimeException('Cannot follow your own pet.');
        }

        if ($pet->isFollowedBy($user)) {
            return false;
        }

        DB::transaction(function () use ($user, $pet): void {
            $pet->followers()->attach($user->id);
            $pet->incrementCounter('followers_count');
            $user->incrementCounter('following_pets_count');
        });

        return true;
    }

    public function unfollow(User $user, Pet $pet): bool
    {
        if (! $pet->isFollowedBy($user)) {
            return false;
        }

        DB::transaction(function () use ($user, $pet): void {
            $pet->followers()->detach($user->id);
            $pet->decrementCounter('followers_count');
            $user->decrementCounter('following_pets_count');
        });

        return true;
    }

    /**
     * Toggle follow state. Returns true if now following, false if unfollowed.
     */
    public function toggle(User $user, Pet $pet): bool
    {
        if ($pet->isFollowedBy($user)) {
            $this->unfollow($user, $pet);

            return false;
        }

        $this->follow($user, $pet);

        return true;
    }
}
