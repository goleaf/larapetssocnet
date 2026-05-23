<?php

namespace App\Http\Controllers\Groups;

use App\Http\Controllers\Controller;
use App\Http\Requests\Groups\TransferGroupOwnershipRequest;
use App\Models\Groups\Group;
use App\Services\GroupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class GroupOwnershipController extends Controller
{
    public function update(TransferGroupOwnershipRequest $request, Group $group, GroupService $groups): RedirectResponse
    {
        try {
            $groups->transferOwnership(
                $request->user(),
                $group,
                (int) $request->validated('membership_id'),
            );
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return back()->with('status', 'Group ownership transferred.');
    }
}
