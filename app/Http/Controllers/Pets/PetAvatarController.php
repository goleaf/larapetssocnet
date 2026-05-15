<?php

namespace App\Http\Controllers\Pets;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pets\UpdatePetAvatarRequest;
use App\Models\Pets\Pet;
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
