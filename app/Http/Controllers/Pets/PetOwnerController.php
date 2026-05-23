<?php

namespace App\Http\Controllers\Pets;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pets\StorePetOwnerRequest;
use App\Models\Identity\User;
use App\Models\Pets\Pet;
use App\Services\PetOwnershipService;
use Illuminate\Http\RedirectResponse;

class PetOwnerController extends Controller
{
    public function store(StorePetOwnerRequest $request, Pet $pet, PetOwnershipService $ownership): RedirectResponse
    {
        $validated = $request->validated();
        $coOwner = User::query()->findOrFail((int) $validated['user_id']);

        $ownership->addCoOwner($pet, $request->user(), $coOwner, $validated);

        return redirect()
            ->route('pets.edit', $pet)
            ->with('status', 'Co-owner added.');
    }
}
