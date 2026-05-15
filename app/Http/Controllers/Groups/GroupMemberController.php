<?php

namespace App\Http\Controllers\Groups;

use App\Actions\Groups\DemoteGroupMemberAction;
use App\Actions\Groups\PromoteGroupMemberAction;
use App\Actions\Groups\RemoveGroupMemberAction;
use App\Actions\Groups\UpdateGroupMemberRoleAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Groups\RemoveGroupMemberRequest;
use App\Http\Requests\Groups\UpdateGroupMemberRoleRequest;
use App\Models\Groups\Group;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

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

    public function promote(Group $group, int $membership, PromoteGroupMemberAction $action): RedirectResponse
    {
        $this->authorize('updateMemberRole', $group);

        try {
            $action->handle(auth()->user(), $group, $membership);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return back()->with('status', 'Member promoted.');
    }

    public function demote(Group $group, int $membership, DemoteGroupMemberAction $action): RedirectResponse
    {
        $this->authorize('updateMemberRole', $group);

        try {
            $action->handle(auth()->user(), $group, $membership);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return back()->with('status', 'Member demoted.');
    }

    public function updateRole(UpdateGroupMemberRoleRequest $request, Group $group, int $membership, UpdateGroupMemberRoleAction $action): RedirectResponse
    {
        $this->authorize('updateMemberRole', $group);

        try {
            $action->handle($request->user(), $group, $membership, (string) $request->validated('role'));
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return back()->with('status', 'Member role updated.');
    }

    public function remove(RemoveGroupMemberRequest $request, Group $group, int $membership, RemoveGroupMemberAction $action): RedirectResponse
    {
        $this->authorize('removeMember', $group);

        try {
            $action->handle($request->user(), $group, $membership);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return back()->with('status', 'Member removed.');
    }
}
