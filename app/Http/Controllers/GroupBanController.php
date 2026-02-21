<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGroupBanRequest;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Schema;

class GroupBanController extends Controller
{
    public function store(StoreGroupBanRequest $request, Group $group): RedirectResponse
    {
        $this->authorize('moderate', $group);

        $actorId = (int) $request->user()->getKey();
        $targetUserId = (int) $request->validated('user_id');

        if ($actorId === $targetUserId) {
            return back()->withErrors([
                'user_id' => 'You cannot ban yourself.',
            ]);
        }

        if ((int) $group->owner_user_id === $targetUserId) {
            return back()->withErrors([
                'user_id' => 'The group owner cannot be banned.',
            ]);
        }

        $membership = GroupMember::query()
            ->where('group_id', $group->getKey())
            ->where('user_id', $targetUserId)
            ->first();

        $actorRank = $this->actorRank($group, $actorId);
        $targetRank = $this->roleRank((string) $membership?->role);

        if ($membership && $actorRank <= $targetRank) {
            abort(403);
        }

        if ($membership && (string) $membership->status === 'banned') {
            return back()->with('status', 'User is already banned.');
        }

        $payload = [
            'role' => 'member',
            'status' => 'banned',
            'joined_at' => null,
            'invited_by' => $actorId,
        ];

        if (Schema::hasColumn('group_members', 'ban_reason')) {
            $payload['ban_reason'] = (string) $request->validated('reason');
        }

        if ($membership) {
            $membership->forceFill($payload)->save();
        } else {
            $group->memberships()->create($payload + [
                'user_id' => $targetUserId,
            ]);
        }

        $this->syncMembersCount($group);

        return back()->with('status', 'User has been banned from the group.');
    }

    public function destroy(Group $group, User $user): RedirectResponse
    {
        $this->authorize('moderate', $group);

        if ((int) $group->owner_user_id === (int) $user->getKey()) {
            abort(403);
        }

        $membership = GroupMember::query()
            ->where('group_id', $group->getKey())
            ->where('user_id', $user->getKey())
            ->where('status', 'banned')
            ->first();

        if (! $membership) {
            return back()->with('status', 'User is not currently banned.');
        }

        $actorRank = $this->actorRank($group, auth()->id());
        $targetRank = $this->roleRank((string) $membership->role);

        if ($actorRank <= $targetRank) {
            abort(403);
        }

        $membership->delete();

        return back()->with('status', 'User ban removed.');
    }

    private function syncMembersCount(Group $group): void
    {
        if (! Schema::hasColumn('groups', 'members_count')) {
            return;
        }

        $group->forceFill([
            'members_count' => (int) $group->memberships()
                ->where(function ($statusQuery): void {
                    $statusQuery
                        ->whereNull('status')
                        ->orWhereIn('status', ['active', 'accepted']);
                })
                ->count(),
        ])->save();
    }

    private function actorRank(Group $group, ?int $actorId): int
    {
        if ($actorId === null) {
            return 0;
        }

        if ((int) $group->owner_user_id === $actorId) {
            return 4;
        }

        $membership = GroupMember::query()
            ->where('group_id', $group->getKey())
            ->where('user_id', $actorId)
            ->where(function ($statusQuery): void {
                $statusQuery
                    ->whereNull('status')
                    ->orWhereIn('status', ['active', 'accepted']);
            })
            ->first();

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
}
