<?php

namespace App\Http\Controllers;

use App\Actions\Groups\BanGroupMemberAction;
use App\Actions\Groups\UnbanGroupMemberAction;
use App\Http\Requests\BanGroupMemberRequest;
use App\Http\Requests\UnbanGroupMemberRequest;
use App\Models\Group;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class GroupBanController extends Controller
{
    public function store(BanGroupMemberRequest $request, Group $group, BanGroupMemberAction $ban): RedirectResponse
    {
        $this->authorize('banMember', $group);

        $actor = $request->user();
        $targetUserId = (int) $request->validated('user_id');
        $reason = $request->validated('reason');

        try {
            $ban->handle($actor, $group, $targetUserId, $reason);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return back()->with('status', 'User has been banned from the group.');
    }

    public function destroy(UnbanGroupMemberRequest $request, Group $group, User $user, UnbanGroupMemberAction $unban): RedirectResponse
    {
        $this->authorize('banMember', $group);

        try {
            $unban->handle($request->user(), $group, $user);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return back()->with('status', 'User ban removed.');
    }
}
