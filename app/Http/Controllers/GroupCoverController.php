<?php

namespace App\Http\Controllers;

use App\Actions\Groups\RemoveGroupCoverAction;
use App\Actions\Groups\UpdateGroupCoverAction;
use App\Http\Requests\UpdateGroupCoverRequest;
use App\Models\Group;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GroupCoverController extends Controller
{
    public function store(UpdateGroupCoverRequest $request, Group $group, UpdateGroupCoverAction $action): RedirectResponse
    {
        $this->authorize('manageCover', $group);

        $action->handle($request->user(), $group, $request->file('cover'));

        return back()->with('status', 'Group cover updated.');
    }

    public function destroy(Request $request, Group $group, RemoveGroupCoverAction $action): RedirectResponse
    {
        $this->authorize('manageCover', $group);

        $action->handle($request->user(), $group);

        return back()->with('status', 'Group cover removed.');
    }
}
