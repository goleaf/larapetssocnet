<?php

namespace App\Http\Controllers;

use App\Actions\Groups\ApproveGroupJoinRequestAction;
use App\Actions\Groups\CancelGroupJoinRequestAction;
use App\Actions\Groups\JoinGroupAction;
use App\Actions\Groups\RejectGroupJoinRequestAction;
use App\Enums\GroupMemberStatus;
use App\Http\Requests\ApproveGroupMembershipRequest;
use App\Http\Requests\CancelGroupJoinRequest;
use App\Http\Requests\JoinGroupRequest;
use App\Http\Requests\RejectGroupMembershipRequest;
use App\Models\Group;
use App\Services\GroupVisibilityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class GroupJoinRequestController extends Controller
{
    public function index(Group $group): RedirectResponse
    {
        $this->authorize('manageMembers', $group);

        return redirect()->route('groups.show', [
            'group' => $group,
            'tab' => 'members',
            'request_tab' => 'pending',
        ]);
    }

    public function store(JoinGroupRequest $request, Group $group, JoinGroupAction $joinGroup, GroupVisibilityService $visibility): RedirectResponse
    {
        $viewer = $request->user();

        if (! $visibility->canJoinGroup($viewer, $group)) {
            return back()->withErrors([
                'group' => 'You cannot join this group.',
            ]);
        }

        try {
            $membership = $joinGroup->handle($viewer, $group, $request->validated('message'));
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        $status = (string) ($membership->status?->value ?? '') === GroupMemberStatus::Pending->value
            ? 'Join request sent.'
            : 'You joined the group.';

        return back()->with('status', $status);
    }

    public function approve(ApproveGroupMembershipRequest $request, Group $group, int $membership, ApproveGroupJoinRequestAction $approve): RedirectResponse
    {
        $this->authorize('manageMembers', $group);

        try {
            $approve->handle($request->user(), $group, $membership);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return back()->with('status', 'Join request approved.');
    }

    public function reject(RejectGroupMembershipRequest $request, Group $group, int $membership, RejectGroupJoinRequestAction $reject): RedirectResponse
    {
        $this->authorize('manageMembers', $group);

        try {
            $reject->handle($request->user(), $group, $membership);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return back()->with('status', 'Join request rejected.');
    }

    public function cancel(CancelGroupJoinRequest $request, Group $group, CancelGroupJoinRequestAction $cancel): RedirectResponse
    {
        try {
            $cancelled = $cancel->handle($request->user(), $group);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return back()->with('status', $cancelled
            ? 'Join request cancelled.'
            : 'No pending request to cancel.');
    }
}
