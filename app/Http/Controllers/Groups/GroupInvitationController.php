<?php

namespace App\Http\Controllers\Groups;

use App\Http\Controllers\Controller;
use App\Http\Requests\Groups\RespondGroupInvitationRequest;
use App\Http\Requests\Groups\StoreGroupInvitationRequest;
use App\Models\Groups\Group;
use App\Models\Groups\GroupInvitation;
use App\Models\Identity\User;
use App\Services\GroupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class GroupInvitationController extends Controller
{
    public function store(StoreGroupInvitationRequest $request, Group $group, GroupService $groups): RedirectResponse
    {
        try {
            $groups->inviteUser(
                $request->user(),
                $group,
                User::query()->findOrFail((int) $request->validated('user_id')),
                $request->validated('message')
            );
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return back()->with('status', 'Group invitation sent.');
    }

    public function accept(RespondGroupInvitationRequest $request, Group $group, GroupInvitation $invitation, GroupService $groups): RedirectResponse
    {
        try {
            $groups->acceptInvitation($request->user(), $group, $invitation);
        } catch (ValidationException $exception) {
            return redirect()
                ->route('groups.index')
                ->withErrors($exception->errors());
        }

        return redirect()
            ->route('groups.show', $group)
            ->with('status', 'Invitation accepted.');
    }

    public function decline(RespondGroupInvitationRequest $request, Group $group, GroupInvitation $invitation, GroupService $groups): RedirectResponse
    {
        try {
            $groups->declineInvitation($request->user(), $group, $invitation);
        } catch (ValidationException $exception) {
            return redirect()
                ->route('groups.index')
                ->withErrors($exception->errors());
        }

        return redirect()
            ->route('groups.index')
            ->with('status', 'Invitation declined.');
    }
}
