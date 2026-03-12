<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePetAvatarRequest;
use App\Models\Pet;
use App\Services\PetAvatarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PetAvatarController extends Controller
{
    public function store(UpdatePetAvatarRequest $request, Pet $pet, PetAvatarService $petAvatarService): RedirectResponse
    {
        $petAvatarService->updateAvatar($request->user(), $pet, $request->file('avatar'));

        return back()->with('status', __('pets.flash.updated'));
    }

    public function destroy(Request $request, Pet $pet, PetAvatarService $petAvatarService): RedirectResponse
    {
        $this->authorize('manageAvatar', $pet);

        $petAvatarService->removeAvatar($request->user(), $pet);

        return back()->with('status', __('pets.flash.updated'));
    }
}
