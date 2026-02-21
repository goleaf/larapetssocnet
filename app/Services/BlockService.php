<?php

namespace App\Services;

use App\Events\UserBlocked;
use App\Events\UserUnblocked;
use App\Exceptions\CannotBlockAdminException;
use App\Exceptions\CannotBlockSelfException;
use App\Models\Block;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class BlockService
{
    public function __construct(private readonly CounterCacheService $counterCacheService) {}

    public function block(User $actor, User $target): void
    {
        if ($actor->is($target)) {
            throw new CannotBlockSelfException();
        }

        if ($target->hasAnyRole(['admin', 'moderator'])) {
            throw new CannotBlockAdminException();
        }

        if ($actor->hasBlocked($target)) {
            return;
        }

        DB::transaction(function () use ($actor, $target): void {
            $wasFollowing = $actor->following()->whereKey($target->getKey())->exists();
            $wasFollowedBy = $actor->followers()->whereKey($target->getKey())->exists();

            $actor->following()->detach($target->getKey());
            $actor->followers()->detach($target->getKey());

            if ($wasFollowing) {
                $this->counterCacheService->safeDecrement($actor, 'following_count');
                $this->counterCacheService->safeDecrement($target, 'followers_count');
            }

            if ($wasFollowedBy) {
                $this->counterCacheService->safeDecrement($target, 'following_count');
                $this->counterCacheService->safeDecrement($actor, 'followers_count');
            }

            Block::query()->firstOrCreate([
                'blocker_id' => $actor->getKey(),
                'blocked_id' => $target->getKey(),
            ], [
                'created_at' => now(),
            ]);
            $actor->increment('blocked_users_count');
            $target->increment('blocked_by_count');

            event(new UserBlocked($actor->fresh(), $target->fresh()));
        });
    }

    public function unblock(User $actor, User $target): void
    {
        if (! $actor->hasBlocked($target)) {
            return;
        }

        DB::transaction(function () use ($actor, $target): void {
            $actor->blocking()->detach($target->getKey());
            $this->counterCacheService->safeDecrement($actor, 'blocked_users_count');
            $this->counterCacheService->safeDecrement($target, 'blocked_by_count');

            event(new UserUnblocked($actor->fresh(), $target->fresh()));
        });
    }

    public function getBlockedUsers(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return $user->blocking()
            ->with('media')
            ->orderByPivot('created_at', 'desc')
            ->paginate($perPage);
    }

    public function isBlocked(User $actor, User $target): bool
    {
        return $actor->hasBlocked($target);
    }

    public function canInteract(User $actor, User $target): bool
    {
        return ! $actor->hasBlockingRelationshipWith($target)
            && ! (bool) $target->is_banned
            && ! $actor->is($target);
    }
}
