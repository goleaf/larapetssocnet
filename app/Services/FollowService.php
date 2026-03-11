<?php

namespace App\Services;

use App\Exceptions\CannotFollowSelfException;
use App\Exceptions\UserBannedException;
use App\Exceptions\UserBlockedException;
use App\Models\Follow;
use App\Models\User;
use App\Notifications\FollowRequestApproved;
use App\Notifications\NewFollower;
use App\Notifications\NewFollowRequest;
use Illuminate\Support\Facades\DB;

class FollowService
{
    public function __construct(private readonly CounterCacheService $counterCacheService) {}

    public function follow(User $actor, User $target): string
    {
        if ($actor->is($target)) {
            throw new CannotFollowSelfException;
        }

        if ($actor->hasBlocked($target) || $target->hasBlocked($actor)) {
            throw new UserBlockedException;
        }

        if ((bool) $target->is_banned) {
            throw new UserBannedException;
        }

        $status = $target->is_private ? 'pending' : 'accepted';

        return DB::transaction(function () use ($actor, $target, $status): string {
            $existing = Follow::query()
                ->where('follower_id', $actor->getKey())
                ->where('following_id', $target->getKey())
                ->first();

            if ($existing?->status === 'accepted') {
                return 'following';
            }

            if ($existing?->status === 'pending') {
                return 'pending';
            }

            Follow::query()->create([
                'follower_id' => $actor->getKey(),
                'following_id' => $target->getKey(),
                'status' => $status,
                'created_at' => now(),
            ]);

            if ($status === 'accepted') {
                $this->counterCacheService->safeIncrement($actor, 'following_count');
                $this->counterCacheService->safeIncrement($target, 'followers_count');
                if ($target->notificationEnabled('new_follower')) {
                    $target->notify(new NewFollower($this->relationLightUser($actor)));
                }

                return 'following';
            }

            $this->counterCacheService->safeIncrement($target, 'follow_requests_count');
            if ($target->notificationEnabled('follow_requests')) {
                $target->notify(new NewFollowRequest($this->relationLightUser($actor)));
            }

            return 'pending';
        });
    }

    public function unfollow(User $actor, User $target): void
    {
        DB::transaction(function () use ($actor, $target): void {
            $row = Follow::query()
                ->where('follower_id', $actor->getKey())
                ->where('following_id', $target->getKey())
                ->first();

            if (! $row) {
                return;
            }

            $row->delete();

            if ($row->status === 'accepted') {
                $this->counterCacheService->safeDecrement($actor, 'following_count');
                $this->counterCacheService->safeDecrement($target, 'followers_count');
            }

            if ($row->status === 'pending') {
                $this->counterCacheService->safeDecrement($target, 'follow_requests_count');
            }
        });
    }

    public function approve(User $owner, User $requester): void
    {
        DB::transaction(function () use ($owner, $requester): void {
            $follow = Follow::query()
                ->where('follower_id', $requester->getKey())
                ->where('following_id', $owner->getKey())
                ->where('status', 'pending')
                ->first();

            if (! $follow) {
                return;
            }

            $follow->update(['status' => 'accepted']);

            $this->counterCacheService->safeIncrement($requester, 'following_count');
            $this->counterCacheService->safeIncrement($owner, 'followers_count');
            $this->counterCacheService->safeDecrement($owner, 'follow_requests_count');

            if ($requester->notificationEnabled('follow_requests')) {
                $requester->notify(new FollowRequestApproved($this->relationLightUser($owner)));
            }
        });
    }

    public function reject(User $owner, User $requester): void
    {
        DB::transaction(function () use ($owner, $requester): void {
            $deleted = Follow::query()
                ->where('follower_id', $requester->getKey())
                ->where('following_id', $owner->getKey())
                ->where('status', 'pending')
                ->delete();

            if ($deleted > 0) {
                $this->counterCacheService->safeDecrement($owner, 'follow_requests_count');
            }
        });
    }

    public function approveAll(User $owner): int
    {
        return DB::transaction(function () use ($owner): int {
            $pending = Follow::query()
                ->where('following_id', $owner->getKey())
                ->where('status', 'pending')
                ->get();

            if ($pending->isEmpty()) {
                return 0;
            }

            Follow::query()
                ->where('following_id', $owner->getKey())
                ->where('status', 'pending')
                ->update(['status' => 'accepted']);

            $owner->increment('followers_count', $pending->count());
            $owner->updateQuietly(['follow_requests_count' => 0]);

            $requesterIds = $pending->pluck('follower_id')->unique()->values();
            User::query()->whereIn('id', $requesterIds)->get()->each(function (User $requester) use ($owner): void {
                $requester->increment('following_count');
                if ($requester->notificationEnabled('follow_requests')) {
                    $requester->notify(new FollowRequestApproved($this->relationLightUser($owner)));
                }
            });

            return $pending->count();
        });
    }

    public function removeFollower(User $owner, User $follower): void
    {
        DB::transaction(function () use ($owner, $follower): void {
            $deleted = Follow::query()
                ->where('follower_id', $follower->getKey())
                ->where('following_id', $owner->getKey())
                ->where('status', 'accepted')
                ->delete();

            if ($deleted > 0) {
                $this->counterCacheService->safeDecrement($follower, 'following_count');
                $this->counterCacheService->safeDecrement($owner, 'followers_count');
            }
        });
    }

    private function relationLightUser(User $user): User
    {
        return $user->withoutRelation([
            'followers',
            'following',
            'followings',
            'acceptedFollowers',
            'acceptedFollowing',
            'acceptedFollowings',
            'sentPendingRequests',
        ]);
    }
}
