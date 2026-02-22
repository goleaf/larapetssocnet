<?php

namespace App\Services;

use App\Models\Pet;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PetFollowService
{
    public function __construct(
        private CounterCacheService $counters,
    ) {}

    public function follow(User $user, Pet $pet): void
    {
        if ($user->id === $pet->user_id) {
            throw new \RuntimeException('Cannot follow your own pet.');
        }

        if ($pet->isFollowedBy($user)) {
            return;
        }

        DB::transaction(function () use ($user, $pet): void {
            $pet->followers()->attach($user->id);
            $pet->increment('followers_count');
        });
    }

    public function unfollow(User $user, Pet $pet): void
    {
        if (! $pet->isFollowedBy($user)) {
            return;
        }

        DB::transaction(function () use ($user, $pet): void {
            $pet->followers()->detach($user->id);
            $this->counters->safeDecrement($pet, 'followers_count');
        });
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
