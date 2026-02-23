<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\GroupMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Schema;

class GroupMemberController extends Controller
{
    public function index(Group $group): RedirectResponse
    {
        $this->authorize('view', $group);

        return redirect()->route('groups.show', [
            'group' => $group,
            'tab' => 'members',
        ]);
    }

    public function promote(Group $group, int $membership): RedirectResponse
    {
        $this->authorize('manageMembers', $group);

        $targetMembership = $this->membershipById($group, $membership);

        if (! $this->isActiveMembership($targetMembership)) {
            return back()->withErrors([
                'group' => 'Only active members can be promoted.',
            ]);
        }

        if ((string) $targetMembership->role === 'owner') {
            abort(403);
        }

        $actorRank = $this->actorRank($group, auth()->id());
        $targetRank = $this->roleRank((string) $targetMembership->role);

        if ($actorRank <= $targetRank) {
            abort(403);
        }

        $nextRole = match ((string) $targetMembership->role) {
            'member' => 'moderator',
            'moderator' => 'admin',
            default => 'admin',
        };

        if ($nextRole === 'admin' && $actorRank < 4) {
            abort(403);
        }

        if ((string) $targetMembership->role === $nextRole) {
            return back()->with('status', 'Member is already at the highest allowed role.');
        }

        $targetMembership->forceFill([
            'role' => $nextRole,
        ])->save();

        return back()->with('status', 'Member promoted.');
    }

    public function demote(Group $group, int $membership): RedirectResponse
    {
        $this->authorize('manageMembers', $group);

        $targetMembership = $this->membershipById($group, $membership);

        if (! $this->isActiveMembership($targetMembership)) {
            return back()->withErrors([
                'group' => 'Only active members can be demoted.',
            ]);
        }

        if ((string) $targetMembership->role === 'owner') {
            abort(403);
        }

        $actorRank = $this->actorRank($group, auth()->id());
        $targetRank = $this->roleRank((string) $targetMembership->role);

        if ($actorRank <= $targetRank) {
            abort(403);
        }

        $nextRole = match ((string) $targetMembership->role) {
            'admin' => 'moderator',
            'moderator' => 'member',
            default => 'member',
        };

        if ((string) $targetMembership->role === $nextRole) {
            return back()->with('status', 'Member is already at the lowest role.');
        }

        $targetMembership->forceFill([
            'role' => $nextRole,
        ])->save();

        return back()->with('status', 'Member demoted.');
    }

    public function remove(Group $group, int $membership): RedirectResponse
    {
        $this->authorize('manageMembers', $group);

        $targetMembership = $this->membershipById($group, $membership);

        if ((string) $targetMembership->role === 'owner') {
            abort(403);
        }

        if ((int) $targetMembership->user_id === (int) auth()->id()) {
            return back()->withErrors([
                'group' => 'Use leave group to remove yourself.',
            ]);
        }

        $actorRank = $this->actorRank($group, auth()->id());
        $targetRank = $this->roleRank((string) $targetMembership->role);

        if ($actorRank <= $targetRank) {
            abort(403);
        }

        $targetMembership->delete();
        $this->syncMembersCount($group);

        return back()->with('status', 'Member removed.');
    }

    private function membershipById(Group $group, int $membershipId): GroupMember
    {
        return GroupMember::query()
            ->where('group_id', $group->getKey())
            ->whereKey($membershipId)
            ->firstOrFail();
    }

    private function isActiveMembership(?GroupMember $membership): bool
    {
        if (! $membership) {
            return false;
        }

        return $membership->status === null
            || in_array((string) $membership->status, ['active', 'accepted'], true);
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
