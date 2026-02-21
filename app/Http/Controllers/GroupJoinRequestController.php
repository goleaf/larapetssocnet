<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGroupJoinRequest;
use App\Models\Group;
use App\Models\GroupMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Schema;

class GroupJoinRequestController extends Controller
{
    public function index(Group $group): RedirectResponse
    {
        $this->authorize('manageMembers', $group);

        return redirect()->route('groups.show', [
            'group' => $group,
            'tab' => 'members',
        ]);
    }

    public function store(StoreGroupJoinRequest $request, Group $group): RedirectResponse
    {
        $viewer = $request->user();

        if ($this->privacy($group) === 'secret') {
            return back()->withErrors([
                'group' => 'Secret groups cannot be joined directly.',
            ]);
        }

        $membership = $this->membership($group, (int) $viewer->getKey());

        if ($membership && $this->isActiveMembership($membership)) {
            return back()->with('status', 'You are already a member.');
        }

        if ($membership && $membership->status === 'pending') {
            return back()->with('status', 'Your join request is already pending.');
        }

        if ($membership && $membership->status === 'banned') {
            return back()->withErrors([
                'group' => 'You are banned from this group.',
            ]);
        }

        if ($membership
            && $membership->status === 'rejected'
            && $membership->updated_at
            && $membership->updated_at->greaterThan(now()->subDays(7))) {
            return back()->withErrors([
                'group' => 'You can request to join again 7 days after a rejection.',
            ]);
        }

        $status = $this->privacy($group) === 'public' ? 'active' : 'pending';
        $payload = [
            'role' => 'member',
            'status' => $status,
            'joined_at' => $status === 'active' ? now() : null,
        ];

        if ($request->filled('message') && Schema::hasColumn('group_members', 'join_request_message')) {
            $payload['join_request_message'] = (string) $request->validated('message');
        }

        if ($membership) {
            $membership->forceFill($payload)->save();
        } else {
            $group->memberships()->create($payload + [
                'user_id' => $viewer->getKey(),
            ]);
        }

        $this->syncMembersCount($group);

        return back()->with('status', $status === 'active'
            ? 'You joined the group.'
            : 'Join request sent.');
    }

    public function approve(Group $group, int $membership): RedirectResponse
    {
        $this->authorize('manageMembers', $group);

        $targetMembership = GroupMember::query()
            ->where('group_id', $group->getKey())
            ->whereKey($membership)
            ->firstOrFail();

        if ((string) $targetMembership->role === 'owner') {
            abort(403);
        }

        $actorRank = $this->actorRank($group, auth()->id());
        $targetRank = $this->roleRank((string) $targetMembership->role);

        if ($actorRank <= $targetRank) {
            abort(403);
        }

        if ($targetMembership->status !== 'pending') {
            return back()->with('status', 'This request is no longer pending.');
        }

        $targetMembership->forceFill([
            'status' => 'active',
            'joined_at' => $targetMembership->joined_at ?: now(),
            'role' => 'member',
        ])->save();

        $this->syncMembersCount($group);

        return back()->with('status', 'Join request approved.');
    }

    public function reject(Group $group, int $membership): RedirectResponse
    {
        $this->authorize('manageMembers', $group);

        $targetMembership = GroupMember::query()
            ->where('group_id', $group->getKey())
            ->whereKey($membership)
            ->firstOrFail();

        if ((string) $targetMembership->role === 'owner') {
            abort(403);
        }

        $actorRank = $this->actorRank($group, auth()->id());
        $targetRank = $this->roleRank((string) $targetMembership->role);

        if ($actorRank <= $targetRank) {
            abort(403);
        }

        if ($targetMembership->status !== 'pending') {
            return back()->with('status', 'This request is no longer pending.');
        }

        $targetMembership->forceFill([
            'status' => 'rejected',
            'joined_at' => null,
            'role' => 'member',
        ])->save();

        return back()->with('status', 'Join request rejected.');
    }

    private function privacy(Group $group): string
    {
        $privacy = strtolower((string) ($group->privacy ?: $group->type ?: 'public'));

        return in_array($privacy, ['public', 'private', 'secret'], true)
            ? $privacy
            : 'public';
    }

    private function membership(Group $group, int $userId): ?GroupMember
    {
        return GroupMember::query()
            ->where('group_id', $group->getKey())
            ->where('user_id', $userId)
            ->first();
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
