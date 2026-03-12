<?php

namespace App\Services;

use App\Events\UserBlocked;
use App\Events\UserUnblocked;
use App\Exceptions\CannotBlockAdminException;
use App\Exceptions\CannotBlockSelfException;
use App\Models\Block;
use App\Models\Follow;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class BlockService
{
    public function __construct(private readonly CounterCacheService $counterCacheService) {}

    public function block(User $actor, User $target): void
    {
        if ($actor->is($target)) {
            throw new CannotBlockSelfException;
        }

        if ($target->hasAnyRole(['admin', 'moderator'])) {
            throw new CannotBlockAdminException;
        }

        DB::transaction(function () use ($actor, $target): void {
            $followRows = Follow::query()
                ->where(function ($query) use ($actor, $target): void {
                    $query
                        ->where('follower_id', $actor->getKey())
                        ->where('following_id', $target->getKey());
                })
                ->orWhere(function ($query) use ($actor, $target): void {
                    $query
                        ->where('follower_id', $target->getKey())
                        ->where('following_id', $actor->getKey());
                })
                ->lockForUpdate()
                ->get();

            foreach ($followRows as $follow) {
                $isActorFollower = (int) $follow->follower_id === (int) $actor->getKey();

                if ($follow->status === 'accepted') {
                    if ($isActorFollower) {
                        $this->counterCacheService->safeDecrement($actor, 'following_count');
                        $this->counterCacheService->safeDecrement($target, 'followers_count');
                    } else {
                        $this->counterCacheService->safeDecrement($target, 'following_count');
                        $this->counterCacheService->safeDecrement($actor, 'followers_count');
                    }
                }

                if ($follow->status === 'pending') {
                    if ($isActorFollower) {
                        $this->counterCacheService->safeDecrement($target, 'follow_requests_count');
                    } else {
                        $this->counterCacheService->safeDecrement($actor, 'follow_requests_count');
                    }
                }
            }

            if ($followRows->isNotEmpty()) {
                Follow::query()
                    ->whereIn('id', $followRows->modelKeys())
                    ->delete();
            }

            $block = Block::query()->firstOrCreate([
                'blocker_id' => $actor->getKey(),
                'blocked_id' => $target->getKey(),
            ], [
                'created_at' => now(),
            ]);

            if ($block->wasRecentlyCreated) {
                $this->counterCacheService->safeIncrement($actor, 'blocked_users_count');
                $this->counterCacheService->safeIncrement($target, 'blocked_by_count');

                event(new UserBlocked($actor->fresh(), $target->fresh()));
            }
        });
    }

    public function unblock(User $actor, User $target): void
    {
        if (! $actor->hasBlocked($target)) {
            return;
        }

        DB::transaction(function () use ($actor, $target): void {
            $detached = $actor->blocking()->detach($target->getKey());

            if ($detached > 0) {
                $this->counterCacheService->safeDecrement($actor, 'blocked_users_count');
                $this->counterCacheService->safeDecrement($target, 'blocked_by_count');

                event(new UserUnblocked($actor->fresh(), $target->fresh()));
            }
        });
    }

    public function getBlockedUsers(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return $user->blocking()
            ->with('media')
            ->orderByPivot('created_at', 'desc')
            ->paginate($perPage)
            ->withQueryString();
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
