<?php

namespace App\Services;

use App\Models\Group;
use App\Models\GroupBan;
use App\Models\GroupJoinRequest;
use App\Models\GroupMember;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class GroupService
{
    private const ACTIVE_STATUSES = ['active', 'accepted'];

    private ?bool $hasGroupJoinRequestsTable = null;

    private ?bool $hasGroupBansTable = null;

    public function join(User|Group $first, User|Group $second): GroupMember
    {
        [$user, $group] = $this->resolveUserAndGroup($first, $second);

        return DB::transaction(function () use ($group, $user): GroupMember {
            $membership = $this->membershipForUser($group, (int) $user->getKey(), true);

            if ($membership !== null && (string) $membership->status === 'banned') {
                throw $this->validation('You are banned from this group.');
            }

            if ($this->hasGroupBansTable()
                && GroupBan::query()
                    ->where('group_id', $group->getKey())
                    ->where('user_id', $user->getKey())
                    ->exists()) {
                throw $this->validation('You are banned from this group.');
            }

            if ($membership !== null && $this->isActiveMembership($membership)) {
                return $membership;
            }

            if ($membership !== null && (string) $membership->status === 'pending') {
                return $membership;
            }

            if ($this->privacy($group) === 'secret') {
                throw $this->validation('Secret groups cannot be joined directly.');
            }

            $status = $this->privacy($group) === 'private' ? 'pending' : 'active';
            $payload = [
                'role' => 'member',
                'status' => $status,
                'joined_at' => $status === 'active' ? now() : null,
            ];

            if ($membership !== null) {
                $membership->forceFill($payload)->save();
            } else {
                $membership = GroupMember::query()->create($payload + [
                    'group_id' => $group->getKey(),
                    'user_id' => $user->getKey(),
                ]);
            }

            if ($status === 'pending' && $this->hasGroupJoinRequestsTable()) {
                GroupJoinRequest::query()->updateOrCreate(
                    [
                        'group_id' => $group->getKey(),
                        'user_id' => $user->getKey(),
                    ],
                    [
                        'status' => 'pending',
                        'reviewed_by' => null,
                        'reviewed_at' => null,
                    ]
                );
            }

            if ($status === 'active' && $this->hasGroupJoinRequestsTable()) {
                GroupJoinRequest::query()
                    ->where('group_id', $group->getKey())
                    ->where('user_id', $user->getKey())
                    ->delete();
            }

            return $membership->fresh();
        });
    }

    public function leave(User|Group $first, User|Group $second): bool
    {
        [$user, $group] = $this->resolveUserAndGroup($first, $second);

        return DB::transaction(function () use ($group, $user): bool {
            $membership = $this->membershipForUser($group, (int) $user->getKey(), true);
            if ($membership === null) {
                return false;
            }

            if ((int) $this->ownerId($group) === (int) $user->getKey() || (string) $membership->role === 'owner') {
                throw $this->validation('Group owners cannot leave the group.');
            }

            $deleted = (bool) $membership->delete();

            if ($deleted && $this->hasGroupJoinRequestsTable()) {
                GroupJoinRequest::query()
                    ->where('group_id', $group->getKey())
                    ->where('user_id', $user->getKey())
                    ->delete();
            }

            return $deleted;
        });
    }

    public function approveRequest(User $actor, Group $group, GroupMember|int $membership): GroupMember
    {
        return DB::transaction(function () use ($actor, $group, $membership): GroupMember {
            $targetMembership = $this->resolveMembership($group, $membership, true);
            $this->assertCanManageMembership($actor, $group, $targetMembership);

            if ((string) $targetMembership->status !== 'pending') {
                return $targetMembership;
            }

            $targetMembership->forceFill([
                'status' => 'active',
                'joined_at' => $targetMembership->joined_at ?: now(),
                'role' => 'member',
            ])->save();

            if ($this->hasGroupJoinRequestsTable()) {
                GroupJoinRequest::query()
                    ->where('group_id', $group->getKey())
                    ->where('user_id', $targetMembership->user_id)
                    ->update([
                        'status' => 'approved',
                        'reviewed_by' => $actor->getKey(),
                        'reviewed_at' => now(),
                    ]);
            }

            return $targetMembership->fresh();
        });
    }

    public function rejectRequest(User $actor, Group $group, GroupMember|int $membership): GroupMember
    {
        return DB::transaction(function () use ($actor, $group, $membership): GroupMember {
            $targetMembership = $this->resolveMembership($group, $membership, true);
            $this->assertCanManageMembership($actor, $group, $targetMembership);

            if ((string) $targetMembership->status !== 'pending') {
                return $targetMembership;
            }

            $targetMembership->forceFill([
                'status' => 'rejected',
                'joined_at' => null,
                'role' => 'member',
            ])->save();

            if ($this->hasGroupJoinRequestsTable()) {
                GroupJoinRequest::query()
                    ->where('group_id', $group->getKey())
                    ->where('user_id', $targetMembership->user_id)
                    ->update([
                        'status' => 'rejected',
                        'reviewed_by' => $actor->getKey(),
                        'reviewed_at' => now(),
                    ]);
            }

            return $targetMembership->fresh();
        });
    }

    public function banUser(User $actor, Group $group, User|GroupMember|int $target, ?string $reason = null): GroupMember
    {
        return DB::transaction(function () use ($actor, $group, $target, $reason): GroupMember {
            $targetUserId = $this->resolveTargetUserId($group, $target);

            if ((int) $actor->getKey() === $targetUserId) {
                throw $this->validation('You cannot ban yourself.');
            }

            if ((int) $this->ownerId($group) === $targetUserId) {
                throw $this->validation('The group owner cannot be banned.');
            }

            $actorRank = $this->actorRank($actor, $group, true);
            if ($actorRank < 2) {
                throw new AuthorizationException('You are not allowed to ban members in this group.');
            }

            $membership = $this->membershipForUser($group, $targetUserId, true);
            $targetRank = $this->roleRank((string) $membership?->role);

            if ($membership !== null && $actorRank <= $targetRank) {
                throw new AuthorizationException('You are not allowed to ban this member.');
            }

            if ($membership !== null && (string) $membership->status === 'banned') {
                return $membership;
            }

            $payload = [
                'role' => 'member',
                'status' => 'banned',
                'joined_at' => null,
                'invited_by' => $actor->getKey(),
            ];

            if (Schema::hasColumn('group_members', 'ban_reason')) {
                $payload['ban_reason'] = $reason;
            }

            if ($membership !== null) {
                $membership->forceFill($payload)->save();
            } else {
                $membership = GroupMember::query()->create($payload + [
                    'group_id' => $group->getKey(),
                    'user_id' => $targetUserId,
                ]);
            }

            if ($this->hasGroupJoinRequestsTable()) {
                GroupJoinRequest::query()
                    ->where('group_id', $group->getKey())
                    ->where('user_id', $targetUserId)
                    ->update([
                        'status' => 'rejected',
                        'reviewed_by' => $actor->getKey(),
                        'reviewed_at' => now(),
                    ]);
            }

            if ($this->hasGroupBansTable()) {
                GroupBan::query()->updateOrCreate(
                    [
                        'group_id' => $group->getKey(),
                        'user_id' => $targetUserId,
                    ],
                    [
                        'banned_by' => $actor->getKey(),
                        'reason' => $reason,
                    ]
                );
            }

            return $membership->fresh();
        });
    }

    public function unbanUser(User $actor, Group $group, User|int $target): bool
    {
        return DB::transaction(function () use ($actor, $group, $target): bool {
            $targetUserId = $target instanceof User ? (int) $target->getKey() : (int) $target;

            if ((int) $this->ownerId($group) === $targetUserId) {
                throw $this->validation('The group owner cannot be banned.');
            }

            $actorRank = $this->actorRank($actor, $group, true);
            if ($actorRank < 2) {
                throw new AuthorizationException('You are not allowed to unban members in this group.');
            }

            $removed = false;
            $membership = $this->membershipForUser($group, $targetUserId, true);
            if ($membership !== null && (string) $membership->status === 'banned') {
                if ($actorRank <= $this->roleRank((string) $membership->role)) {
                    throw new AuthorizationException('You are not allowed to unban this member.');
                }

                $membership->delete();
                $removed = true;
            }

            if ($this->hasGroupBansTable()) {
                $removed = GroupBan::query()
                    ->where('group_id', $group->getKey())
                    ->where('user_id', $targetUserId)
                    ->delete() > 0 || $removed;
            }

            return $removed;
        });
    }

    public function promoteUser(User $actor, Group $group, GroupMember|int $membership): GroupMember
    {
        return DB::transaction(function () use ($actor, $group, $membership): GroupMember {
            $targetMembership = $this->resolveMembership($group, $membership, true);
            $this->assertPromotableMembership($targetMembership);

            $actorRank = $this->actorRank($actor, $group, true);
            $targetRank = $this->roleRank((string) $targetMembership->role);

            if ($actorRank <= $targetRank) {
                throw new AuthorizationException('You are not allowed to promote this member.');
            }

            $nextRole = match ((string) $targetMembership->role) {
                'member' => 'moderator',
                'moderator' => 'admin',
                default => 'admin',
            };

            if ($nextRole === 'admin' && $actorRank < 4) {
                throw new AuthorizationException('Only the group owner can promote members to admin.');
            }

            if ((string) $targetMembership->role !== $nextRole) {
                $targetMembership->forceFill(['role' => $nextRole])->save();
            }

            return $targetMembership->fresh();
        });
    }

    public function demoteUser(User $actor, Group $group, GroupMember|int $membership): GroupMember
    {
        return DB::transaction(function () use ($actor, $group, $membership): GroupMember {
            $targetMembership = $this->resolveMembership($group, $membership, true);
            $this->assertPromotableMembership($targetMembership);

            $actorRank = $this->actorRank($actor, $group, true);
            $targetRank = $this->roleRank((string) $targetMembership->role);

            if ($actorRank <= $targetRank) {
                throw new AuthorizationException('You are not allowed to demote this member.');
            }

            $nextRole = match ((string) $targetMembership->role) {
                'admin' => 'moderator',
                'moderator' => 'member',
                default => 'member',
            };

            if ((string) $targetMembership->role !== $nextRole) {
                $targetMembership->forceFill(['role' => $nextRole])->save();
            }

            return $targetMembership->fresh();
        });
    }

    public function isBanned(Group $group, User|int $user): bool
    {
        return $this->isUserBanned($group, $user);
    }

    public function isUserBanned(Group $group, User|int $user): bool
    {
        $userId = $user instanceof User ? (int) $user->getKey() : (int) $user;

        $isBannedMember = GroupMember::query()
            ->where('group_id', $group->getKey())
            ->where('user_id', $userId)
            ->where('status', 'banned')
            ->exists();

        if ($isBannedMember) {
            return true;
        }

        return $this->hasGroupBansTable()
            && GroupBan::query()
                ->where('group_id', $group->getKey())
                ->where('user_id', $userId)
                ->exists();
    }

    public function ensureNotBanned(Group $group, User|int $user): void
    {
        if ($this->isUserBanned($group, $user)) {
            throw $this->validation('You are banned from this group.');
        }
    }

    private function resolveUserAndGroup(User|Group $first, User|Group $second): array
    {
        if ($first instanceof User && $second instanceof Group) {
            return [$first, $second];
        }

        if ($first instanceof Group && $second instanceof User) {
            return [$second, $first];
        }

        throw new \InvalidArgumentException('Expected one User and one Group.');
    }

    private function resolveTargetUserId(Group $group, User|GroupMember|int $target): int
    {
        if ($target instanceof User) {
            return (int) $target->getKey();
        }

        if ($target instanceof GroupMember) {
            if ((int) $target->group_id !== (int) $group->getKey()) {
                throw $this->validation('Membership does not belong to this group.');
            }

            return (int) $target->user_id;
        }

        return (int) $target;
    }

    private function resolveMembership(Group $group, GroupMember|int $membership, bool $lockForUpdate = false): GroupMember
    {
        if ($membership instanceof GroupMember) {
            if ((int) $membership->group_id !== (int) $group->getKey()) {
                throw $this->validation('Membership does not belong to this group.');
            }

            if (! $lockForUpdate) {
                return $membership;
            }

            return GroupMember::query()
                ->where('group_id', $group->getKey())
                ->whereKey($membership->getKey())
                ->lockForUpdate()
                ->firstOrFail();
        }

        $query = GroupMember::query()
            ->where('group_id', $group->getKey())
            ->whereKey($membership);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->firstOrFail();
    }

    private function membershipForUser(Group $group, int $userId, bool $lockForUpdate = false): ?GroupMember
    {
        $query = GroupMember::query()
            ->where('group_id', $group->getKey())
            ->where('user_id', $userId);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    private function assertCanManageMembership(User $actor, Group $group, GroupMember $membership): void
    {
        if ((string) $membership->role === 'owner') {
            throw new AuthorizationException('The owner cannot be modified.');
        }

        $actorRank = $this->actorRank($actor, $group, true);
        $targetRank = $this->roleRank((string) $membership->role);

        if ($actorRank <= $targetRank) {
            throw new AuthorizationException('You are not allowed to manage this member.');
        }
    }

    private function assertPromotableMembership(GroupMember $membership): void
    {
        if (! $this->isActiveMembership($membership)) {
            throw $this->validation('Only active members can be changed.');
        }

        if ((string) $membership->role === 'owner') {
            throw new AuthorizationException('The owner cannot be modified.');
        }
    }

    private function actorRank(User $actor, Group $group, bool $lockForUpdate = false): int
    {
        if ((int) $actor->getKey() === (int) $this->ownerId($group)) {
            return 4;
        }

        $query = GroupMember::query()
            ->where('group_id', $group->getKey())
            ->where('user_id', $actor->getKey())
            ->where(function (Builder $statusQuery): void {
                $statusQuery
                    ->whereNull('status')
                    ->orWhereIn('status', self::ACTIVE_STATUSES);
            });

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $membership = $query->first();

        return $this->roleRank((string) $membership?->role);
    }

    private function roleRank(string $role): int
    {
        return match ($role) {
            'owner' => 4,
            'admin' => 3,
            'moderator' => 2,
            default => 1,
        };
    }

    private function ownerId(Group $group): ?int
    {
        $ownerUserId = $group->getAttribute('owner_user_id');
        if ($ownerUserId !== null) {
            return (int) $ownerUserId;
        }

        $ownerId = $group->getAttribute('owner_id');

        return $ownerId !== null ? (int) $ownerId : null;
    }

    private function isActiveMembership(GroupMember $membership): bool
    {
        $status = $membership->status;

        return $status === null || in_array((string) $status, self::ACTIVE_STATUSES, true);
    }

    private function privacy(Group $group): string
    {
        $privacy = strtolower((string) ($group->privacy ?: $group->type ?: 'public'));

        return in_array($privacy, ['public', 'private', 'secret'], true) ? $privacy : 'public';
    }

    private function hasGroupJoinRequestsTable(): bool
    {
        if ($this->hasGroupJoinRequestsTable === null) {
            $this->hasGroupJoinRequestsTable = Schema::hasTable('group_join_requests');
        }

        return $this->hasGroupJoinRequestsTable;
    }

    private function hasGroupBansTable(): bool
    {
        if ($this->hasGroupBansTable === null) {
            $this->hasGroupBansTable = Schema::hasTable('group_bans');
        }

        return $this->hasGroupBansTable;
    }

    private function validation(string $message): ValidationException
    {
        return ValidationException::withMessages([
            'group' => $message,
        ]);
    }
}
