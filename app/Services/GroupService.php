<?php

namespace App\Services;

use App\Enums\GroupInvitationStatus;
use App\Enums\GroupMemberRole;
use App\Enums\GroupMemberStatus;
use App\Models\Content\Post;
use App\Models\Groups\Group;
use App\Models\Groups\GroupBan;
use App\Models\Groups\GroupInvitation;
use App\Models\Groups\GroupJoinRequest;
use App\Models\Groups\GroupMember;
use App\Models\Identity\User;
use App\Notifications\Database\Groups\GroupInvitationReceived;
use App\Notifications\Database\Groups\GroupJoinApproved;
use App\Notifications\Database\Groups\GroupJoinRequest as GroupJoinRequestNotification;
use App\Notifications\Database\Groups\GroupModerationAlert;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class GroupService
{
    private ?bool $hasGroupJoinRequestsTable = null;

    private ?bool $hasGroupBansTable = null;

    private ?bool $hasGroupInvitationsTable = null;

    public function __construct(
        private readonly SyncGroupCountersService $counters,
    ) {}

    public function join(User|Group $first, User|Group $second, ?string $message = null): GroupMember
    {
        [$user, $group] = $this->resolveUserAndGroup($first, $second);

        return DB::transaction(function () use ($group, $user, $message): GroupMember {
            if ($group->isArchived()) {
                throw $this->validation('Archived groups are read-only.');
            }

            $membership = $this->membershipForUser($group, (int) $user->getKey(), true);

            if ($membership instanceof GroupMember && (string) ($membership->status?->value ?? '') === GroupMemberStatus::Banned->value) {
                throw $this->validation('You are banned from this group.');
            }

            if ($this->hasGroupBansTable()
                && GroupBan::query()
                    ->where('group_id', $group->getKey())
                    ->where('user_id', $user->getKey())
                    ->exists()) {
                throw $this->validation('You are banned from this group.');
            }

            if ($membership instanceof GroupMember && $this->isActiveMembership($membership)) {
                return $membership;
            }

            if ($membership instanceof GroupMember && (string) ($membership->status?->value ?? '') === GroupMemberStatus::Pending->value) {
                return $membership;
            }

            if ($this->privacy($group) === 'secret') {
                throw $this->validation('Secret groups cannot be joined directly.');
            }

            if ($membership
                && (string) ($membership->status?->value ?? '') === GroupMemberStatus::Rejected->value
                && $membership->updated_at
                && $membership->updated_at->greaterThan(now()->subDays(7))) {
                throw $this->validation('You can request to join again 7 days after a rejection.');
            }

            $status = $this->privacy($group) === 'private'
                ? GroupMemberStatus::Pending->value
                : GroupMemberStatus::Active->value;

            $payload = [
                'role' => GroupMemberRole::Member->value,
                'status' => $status,
                'joined_at' => $status === GroupMemberStatus::Active->value ? now() : null,
            ];

            if ($membership instanceof GroupMember) {
                $membership->forceFill($payload)->save();
            } else {
                $membership = GroupMember::query()->create($payload + [
                    'group_id' => $group->getKey(),
                    'user_id' => $user->getKey(),
                ]);
            }

            if ($status === GroupMemberStatus::Pending->value && $this->hasGroupJoinRequestsTable()) {
                GroupJoinRequest::query()->updateOrCreate(
                    [
                        'group_id' => $group->getKey(),
                        'user_id' => $user->getKey(),
                    ],
                    [
                        'status' => 'pending',
                        'reviewed_by' => null,
                        'reviewed_at' => null,
                        'message' => $message,
                    ]
                );
            }

            if ($status === GroupMemberStatus::Active->value && $this->hasGroupJoinRequestsTable()) {
                GroupJoinRequest::query()
                    ->where('group_id', $group->getKey())
                    ->where('user_id', $user->getKey())
                    ->delete();
            }

            $this->counters->syncMembersCount($group);

            if ($status === GroupMemberStatus::Pending->value) {
                DB::afterCommit(function () use ($group, $user): void {
                    $this->notifyJoinRequest($group, $user);
                });
            }

            return $membership->fresh();
        });
    }

    public function inviteUser(User $actor, Group $group, User $invitee, ?string $message = null): GroupInvitation
    {
        return DB::transaction(function () use ($actor, $group, $invitee, $message): GroupInvitation {
            if (! $this->hasGroupInvitationsTable()) {
                throw $this->validation('Group invitations are not available.');
            }

            if ($group->isArchived()) {
                throw $this->validation('Archived groups are read-only.');
            }

            if ((int) $actor->getKey() === (int) $invitee->getKey()) {
                throw $this->validation('You cannot invite yourself.');
            }

            if ($this->actorRank($actor, $group, true) < GroupMemberRole::Admin->rank()) {
                throw new AuthorizationException('You are not allowed to invite members to this group.');
            }

            if ($this->isUserBanned($group, $invitee)) {
                throw $this->validation('This user cannot be invited to the group.');
            }

            $membership = $this->membershipForUser($group, (int) $invitee->getKey(), true);

            if ($membership instanceof GroupMember && $this->isActiveMembership($membership)) {
                throw $this->validation('This user is already a group member.');
            }

            $invitation = GroupInvitation::query()->updateOrCreate(
                [
                    'group_id' => $group->getKey(),
                    'invited_user_id' => $invitee->getKey(),
                ],
                [
                    'invited_by_user_id' => $actor->getKey(),
                    'status' => GroupInvitationStatus::Pending->value,
                    'role' => GroupMemberRole::Member->value,
                    'message' => $message,
                    'responded_at' => null,
                    'expires_at' => now()->addDays(14),
                ],
            );

            $invitee->notify(new GroupInvitationReceived($group, $invitation, $actor));

            return $invitation->fresh();
        });
    }

    public function acceptInvitation(User $invitee, Group $group, GroupInvitation|int $invitation): GroupMember
    {
        return DB::transaction(function () use ($invitee, $group, $invitation): GroupMember {
            $invitation = $this->resolveInvitation($group, $invitation, true);

            if ((int) $invitation->invited_user_id !== (int) $invitee->getKey()) {
                throw $this->validation('This invitation belongs to another user.');
            }

            if (! $invitation->isPending()) {
                throw $this->validation('This invitation is no longer active.');
            }

            if ($group->isArchived()) {
                throw $this->validation('Archived groups are read-only.');
            }

            if ($this->isUserBanned($group, $invitee)) {
                throw $this->validation('You are banned from this group.');
            }

            $membership = $this->membershipForUser($group, (int) $invitee->getKey(), true);

            $payload = [
                'role' => $invitation->roleValue(),
                'status' => GroupMemberStatus::Active->value,
                'joined_at' => now(),
                'invited_by' => $invitation->invited_by_user_id,
            ];

            if ($membership instanceof GroupMember) {
                $membership->forceFill($payload)->save();
            } else {
                $membership = GroupMember::query()->create($payload + [
                    'group_id' => $group->getKey(),
                    'user_id' => $invitee->getKey(),
                ]);
            }

            $invitation->forceFill([
                'status' => GroupInvitationStatus::Accepted->value,
                'responded_at' => now(),
            ])->save();

            if ($this->hasGroupJoinRequestsTable()) {
                GroupJoinRequest::query()
                    ->where('group_id', $group->getKey())
                    ->where('user_id', $invitee->getKey())
                    ->delete();
            }

            $this->counters->syncMembersCount($group);

            return $membership->fresh();
        });
    }

    public function declineInvitation(User $invitee, Group $group, GroupInvitation|int $invitation): GroupInvitation
    {
        return DB::transaction(function () use ($invitee, $group, $invitation): GroupInvitation {
            $invitation = $this->resolveInvitation($group, $invitation, true);

            if ((int) $invitation->invited_user_id !== (int) $invitee->getKey()) {
                throw $this->validation('This invitation belongs to another user.');
            }

            if (! $invitation->isPending()) {
                return $invitation;
            }

            $invitation->forceFill([
                'status' => GroupInvitationStatus::Declined->value,
                'responded_at' => now(),
            ])->save();

            return $invitation->fresh();
        });
    }

    public function transferOwnership(User $actor, Group $group, GroupMember|int $membership): Group
    {
        return DB::transaction(function () use ($actor, $group, $membership): Group {
            if ((int) $this->ownerId($group) !== (int) $actor->getKey()) {
                throw new AuthorizationException('Only the current owner can transfer group ownership.');
            }

            $targetMembership = $this->resolveMembership($group, $membership, true);

            if (! $this->isActiveMembership($targetMembership)) {
                throw $this->validation('Ownership can only be transferred to an active member.');
            }

            if ((int) $targetMembership->user_id === (int) $actor->getKey()) {
                throw $this->validation('Choose another active member to receive ownership.');
            }

            GroupMember::query()
                ->where('group_id', $group->getKey())
                ->where('role', GroupMemberRole::Owner->value)
                ->whereKeyNot($targetMembership->getKey())
                ->update(['role' => GroupMemberRole::Admin->value]);

            $previousOwnerMembership = $this->membershipForUser($group, (int) $actor->getKey(), true);

            if ($previousOwnerMembership instanceof GroupMember) {
                $previousOwnerMembership->forceFill([
                    'role' => GroupMemberRole::Admin->value,
                    'status' => GroupMemberStatus::Active->value,
                    'joined_at' => $previousOwnerMembership->joined_at ?: now(),
                ])->save();
            } else {
                GroupMember::query()->create([
                    'group_id' => $group->getKey(),
                    'user_id' => $actor->getKey(),
                    'role' => GroupMemberRole::Admin->value,
                    'status' => GroupMemberStatus::Active->value,
                    'joined_at' => now(),
                ]);
            }

            $targetMembership->forceFill([
                'role' => GroupMemberRole::Owner->value,
                'status' => GroupMemberStatus::Active->value,
                'joined_at' => $targetMembership->joined_at ?: now(),
            ])->save();

            $group->forceFill([
                'owner_id' => $targetMembership->user_id,
                'owner_user_id' => $targetMembership->user_id,
            ])->save();

            return $group->fresh();
        });
    }

    public function notifyPostRemoved(User $moderator, Group $group, Post $post): void
    {
        if ((int) $post->user_id === (int) $moderator->getKey()) {
            return;
        }

        $author = User::query()
            ->whereKey($post->user_id)
            ->first(['id', 'name', 'username']);

        if (! $author instanceof User) {
            return;
        }

        $author->notify(new GroupModerationAlert($group, $post, $moderator, 'removed'));
    }

    public function leave(User|Group $first, User|Group $second): bool
    {
        [$user, $group] = $this->resolveUserAndGroup($first, $second);

        return DB::transaction(function () use ($group, $user): bool {
            $membership = $this->membershipForUser($group, (int) $user->getKey(), true);
            if (! $membership instanceof GroupMember) {
                return false;
            }

            if ((int) $this->ownerId($group) === (int) $user->getKey()
                || (string) ($membership->role?->value ?? '') === GroupMemberRole::Owner->value) {
                throw $this->validation('Group owners cannot leave the group.');
            }

            $deleted = (bool) $membership->delete();

            if ($deleted && $this->hasGroupJoinRequestsTable()) {
                GroupJoinRequest::query()
                    ->where('group_id', $group->getKey())
                    ->where('user_id', $user->getKey())
                    ->delete();
            }

            if ($deleted) {
                $this->counters->syncMembersCount($group);
            }

            return $deleted;
        });
    }

    public function cancelRequest(User $user, Group $group): bool
    {
        return DB::transaction(function () use ($user, $group): bool {
            $membership = $this->membershipForUser($group, (int) $user->getKey(), true);
            if (! $membership instanceof GroupMember) {
                return false;
            }

            if ((string) ($membership->status?->value ?? '') !== GroupMemberStatus::Pending->value) {
                return false;
            }

            $deleted = (bool) $membership->delete();

            if ($this->hasGroupJoinRequestsTable()) {
                GroupJoinRequest::query()
                    ->where('group_id', $group->getKey())
                    ->where('user_id', $user->getKey())
                    ->delete();
            }

            if ($deleted) {
                $this->counters->syncMembersCount($group);
            }

            return $deleted;
        });
    }

    public function approveRequest(User $actor, Group $group, GroupMember|int $membership): GroupMember
    {
        return DB::transaction(function () use ($actor, $group, $membership): GroupMember {
            $targetMembership = $this->resolveMembership($group, $membership, true);
            $this->assertCanManageMembership($actor, $group, $targetMembership);

            if ((string) ($targetMembership->status?->value ?? '') !== GroupMemberStatus::Pending->value) {
                return $targetMembership;
            }

            $targetMembership->forceFill([
                'status' => GroupMemberStatus::Active->value,
                'joined_at' => $targetMembership->joined_at ?: now(),
                'role' => GroupMemberRole::Member->value,
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

            $this->counters->syncMembersCount($group);

            DB::afterCommit(function () use ($actor, $group, $targetMembership): void {
                $this->notifyJoinApproved($actor, $group, $targetMembership);
            });

            return $targetMembership->fresh();
        });
    }

    public function rejectRequest(User $actor, Group $group, GroupMember|int $membership): GroupMember
    {
        return DB::transaction(function () use ($actor, $group, $membership): GroupMember {
            $targetMembership = $this->resolveMembership($group, $membership, true);
            $this->assertCanManageMembership($actor, $group, $targetMembership);

            if ((string) ($targetMembership->status?->value ?? '') !== GroupMemberStatus::Pending->value) {
                return $targetMembership;
            }

            $targetMembership->forceFill([
                'status' => GroupMemberStatus::Rejected->value,
                'joined_at' => null,
                'role' => GroupMemberRole::Member->value,
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

            $this->counters->syncMembersCount($group);

            return $targetMembership->fresh();
        });
    }

    public function removeMember(User $actor, Group $group, GroupMember|int $membership): bool
    {
        return DB::transaction(function () use ($actor, $group, $membership): bool {
            $targetMembership = $this->resolveMembership($group, $membership, true);
            $this->assertCanManageMembership($actor, $group, $targetMembership);

            if ((int) $targetMembership->user_id === (int) $actor->getKey()) {
                throw $this->validation('Use leave group to remove yourself.');
            }

            $deleted = (bool) $targetMembership->delete();

            if ($this->hasGroupJoinRequestsTable()) {
                GroupJoinRequest::query()
                    ->where('group_id', $group->getKey())
                    ->where('user_id', $targetMembership->user_id)
                    ->delete();
            }

            if ($deleted) {
                $this->counters->syncMembersCount($group);
            }

            return $deleted;
        });
    }

    public function updateRole(User $actor, Group $group, GroupMember|int $membership, string $role): GroupMember
    {
        return DB::transaction(function () use ($actor, $group, $membership, $role): GroupMember {
            $targetMembership = $this->resolveMembership($group, $membership, true);
            $this->assertPromotableMembership($targetMembership);

            $desiredRole = GroupMemberRole::tryFrom($role);

            if (! $desiredRole) {
                throw $this->validation('Invalid role requested.');
            }

            if ($desiredRole === GroupMemberRole::Owner) {
                throw new AuthorizationException('The owner cannot be assigned via member management.');
            }

            $actorRank = $this->actorRank($actor, $group, true);
            $targetRank = $this->roleRank((string) ($targetMembership->role?->value ?? ''));
            $desiredRank = $desiredRole->rank();

            if ($actorRank <= $targetRank) {
                throw new AuthorizationException('You are not allowed to manage this member.');
            }

            if ($actorRank < GroupMemberRole::Owner->rank() && $desiredRole === GroupMemberRole::Admin) {
                throw new AuthorizationException('Only the group owner can promote members to admin.');
            }

            if ($actorRank <= $desiredRank) {
                throw new AuthorizationException('You are not allowed to assign this role.');
            }

            if ((string) ($targetMembership->role?->value ?? '') !== $desiredRole->value) {
                $targetMembership->forceFill(['role' => $desiredRole->value])->save();
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
            $targetRank = $this->roleRank((string) ($membership?->role?->value ?? ''));

            if ($membership instanceof GroupMember && $actorRank <= $targetRank) {
                throw new AuthorizationException('You are not allowed to ban this member.');
            }

            if ($membership instanceof GroupMember && (string) ($membership->status?->value ?? '') === GroupMemberStatus::Banned->value) {
                return $membership;
            }

            $payload = [
                'role' => GroupMemberRole::Member->value,
                'status' => GroupMemberStatus::Banned->value,
                'joined_at' => null,
                'invited_by' => $actor->getKey(),
            ];

            if (Schema::hasColumn('group_members', 'ban_reason')) {
                $payload['ban_reason'] = $reason;
            }

            if ($membership instanceof GroupMember) {
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

            $this->counters->syncMembersCount($group);

            return $membership->fresh();
        });
    }

    public function unbanUser(User $actor, Group $group, User|int $target): bool
    {
        return DB::transaction(function () use ($actor, $group, $target): bool {
            $targetUserId = $target instanceof User ? (int) $target->getKey() : $target;

            if ((int) $this->ownerId($group) === $targetUserId) {
                throw $this->validation('The group owner cannot be banned.');
            }

            $actorRank = $this->actorRank($actor, $group, true);
            if ($actorRank < 2) {
                throw new AuthorizationException('You are not allowed to unban members in this group.');
            }

            $removed = false;
            $membership = $this->membershipForUser($group, $targetUserId, true);
            if ($membership instanceof GroupMember && (string) ($membership->status?->value ?? '') === GroupMemberStatus::Banned->value) {
                if ($actorRank <= $this->roleRank((string) ($membership->role?->value ?? ''))) {
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

            if ($removed) {
                $this->counters->syncMembersCount($group);
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
            $targetRank = $this->roleRank((string) ($targetMembership->role?->value ?? ''));

            if ($actorRank <= $targetRank) {
                throw new AuthorizationException('You are not allowed to promote this member.');
            }

            $currentRole = GroupMemberRole::tryFrom((string) ($targetMembership->role?->value ?? '')) ?? GroupMemberRole::Member;
            $nextRole = ($currentRole->nextPromotion() ?? GroupMemberRole::Admin)->value;

            if ($nextRole === GroupMemberRole::Admin->value && $actorRank < GroupMemberRole::Owner->rank()) {
                throw new AuthorizationException('Only the group owner can promote members to admin.');
            }

            if ((string) ($targetMembership->role?->value ?? '') !== $nextRole) {
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
            $targetRank = $this->roleRank((string) ($targetMembership->role?->value ?? ''));

            if ($actorRank <= $targetRank) {
                throw new AuthorizationException('You are not allowed to demote this member.');
            }

            $currentRole = GroupMemberRole::tryFrom((string) ($targetMembership->role?->value ?? '')) ?? GroupMemberRole::Member;
            $nextRole = ($currentRole->nextDemotion() ?? GroupMemberRole::Member)->value;

            if ((string) ($targetMembership->role?->value ?? '') !== $nextRole) {
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
        $userId = $user instanceof User ? (int) $user->getKey() : $user;

        $isBannedMember = GroupMember::query()
            ->where('group_id', $group->getKey())
            ->where('user_id', $userId)
            ->where('status', GroupMemberStatus::Banned->value)
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

        throw new InvalidArgumentException('Expected one User and one Group.');
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

        return $target;
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

    private function resolveInvitation(Group $group, GroupInvitation|int $invitation, bool $lockForUpdate = false): GroupInvitation
    {
        if ($invitation instanceof GroupInvitation) {
            if ((int) $invitation->group_id !== (int) $group->getKey()) {
                throw $this->validation('Invitation does not belong to this group.');
            }

            if (! $lockForUpdate) {
                return $invitation;
            }

            return GroupInvitation::query()
                ->where('group_id', $group->getKey())
                ->whereKey($invitation->getKey())
                ->lockForUpdate()
                ->firstOrFail();
        }

        $query = GroupInvitation::query()
            ->where('group_id', $group->getKey())
            ->whereKey($invitation);

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
        if ((string) ($membership->role?->value ?? '') === GroupMemberRole::Owner->value) {
            throw new AuthorizationException('The owner cannot be modified.');
        }

        $actorRank = $this->actorRank($actor, $group, true);
        $targetRank = $this->roleRank((string) ($membership->role?->value ?? ''));

        if ($actorRank <= $targetRank) {
            throw new AuthorizationException('You are not allowed to manage this member.');
        }
    }

    private function assertPromotableMembership(GroupMember $membership): void
    {
        if (! $this->isActiveMembership($membership)) {
            throw $this->validation('Only active members can be changed.');
        }

        if ((string) ($membership->role?->value ?? '') === GroupMemberRole::Owner->value) {
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
                    ->orWhereIn('status', GroupMemberStatus::activeValues());
            });

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $membership = $query->first();

        return $this->roleRank((string) ($membership?->role?->value ?? ''));
    }

    private function roleRank(string $role): int
    {
        return GroupMemberRole::tryFrom($role)?->rank() ?? GroupMemberRole::Member->rank();
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
        $status = $membership->getAttribute('status');

        return $status === null
            || ($status instanceof GroupMemberStatus && $status->isActive());
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

    private function hasGroupInvitationsTable(): bool
    {
        if ($this->hasGroupInvitationsTable === null) {
            $this->hasGroupInvitationsTable = Schema::hasTable('group_invitations');
        }

        return $this->hasGroupInvitationsTable;
    }

    private function validation(string $message): ValidationException
    {
        return ValidationException::withMessages([
            'group' => $message,
        ]);
    }

    private function notifyJoinRequest(Group $group, User $requester): void
    {
        $recipientIds = collect([(int) $this->ownerId($group)])
            ->filter(fn (?int $id): bool => $id !== null && $id > 0);

        $adminIds = GroupMember::query()
            ->forGroup((int) $group->getKey())
            ->active()
            ->where('role', GroupMemberRole::Admin->value)
            ->pluck('user_id');

        $recipientIds = $recipientIds->merge($adminIds)->unique()->values();

        if ($recipientIds->isEmpty()) {
            return;
        }

        $recipients = User::query()
            ->whereIn('id', $recipientIds->all())
            ->get(['id', 'name', 'username']);

        Notification::send($recipients, new GroupJoinRequestNotification($requester, $group));
    }

    private function notifyJoinApproved(User $approver, Group $group, GroupMember $membership): void
    {
        $user = User::query()
            ->whereKey($membership->user_id)
            ->first(['id', 'name', 'username']);

        if (! $user) {
            return;
        }

        $user->notify(new GroupJoinApproved($approver, $group));
    }
}
