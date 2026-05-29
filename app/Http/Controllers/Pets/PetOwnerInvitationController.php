<?php

namespace App\Http\Controllers\Pets;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pets\RespondPetOwnerInvitationRequest;
use App\Http\Requests\Pets\StorePetOwnerInvitationRequest;
use App\Models\Identity\User;
use App\Models\Pets\Pet;
use App\Models\Pets\PetOwnerInvitation;
use App\Services\Pets\PetOwnerInvitationService;
use Illuminate\Http\RedirectResponse;

class PetOwnerInvitationController extends Controller
{
    public function store(StorePetOwnerInvitationRequest $request, Pet $pet, PetOwnerInvitationService $invitations): RedirectResponse
    {
        $validated = $request->validated();

        $invitations->invite(
            $request->user(),
            $pet,
            User::query()->findOrFail((int) $validated['user_id']),
            (string) $validated['role'],
        );

        return back()->with('status', 'Pet co-owner invitation sent.');
    }

    public function accept(RespondPetOwnerInvitationRequest $request, Pet $pet, PetOwnerInvitation $invitation, PetOwnerInvitationService $invitations): RedirectResponse
    {
        $invitations->accept($request->user(), $pet, $invitation);

        return redirect()
            ->route('pets.show', $pet)
            ->with('status', 'Pet co-owner invitation accepted.');
    }

    public function decline(RespondPetOwnerInvitationRequest $request, Pet $pet, PetOwnerInvitation $invitation, PetOwnerInvitationService $invitations): RedirectResponse
    {
        $invitations->decline($request->user(), $pet, $invitation);

        return redirect()
            ->route('pets.index')
            ->with('status', 'Pet co-owner invitation declined.');
    }
}
