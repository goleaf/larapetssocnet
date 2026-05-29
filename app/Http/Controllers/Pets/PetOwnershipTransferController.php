<?php

namespace App\Http\Controllers\Pets;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pets\RespondPetOwnershipTransferRequest;
use App\Http\Requests\Pets\StorePetOwnershipTransferRequest;
use App\Models\Identity\User;
use App\Models\Pets\Pet;
use App\Models\Pets\PetOwnershipTransfer;
use App\Services\Pets\PetOwnershipTransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PetOwnershipTransferController extends Controller
{
    public function store(StorePetOwnershipTransferRequest $request, Pet $pet, PetOwnershipTransferService $transfers): RedirectResponse
    {
        $transfers->initiate(
            $request->user(),
            $pet,
            User::query()->findOrFail((int) $request->validated('user_id')),
        );

        return back()->with('status', 'Pet ownership transfer request sent.');
    }

    public function accept(RespondPetOwnershipTransferRequest $request, Pet $pet, PetOwnershipTransfer $transfer, PetOwnershipTransferService $transfers): RedirectResponse
    {
        $transfers->accept($request->user(), $pet, $transfer);

        return redirect()
            ->route('pets.show', $pet->fresh())
            ->with('status', 'Pet ownership transfer accepted.');
    }

    public function decline(RespondPetOwnershipTransferRequest $request, Pet $pet, PetOwnershipTransfer $transfer, PetOwnershipTransferService $transfers): RedirectResponse
    {
        $transfers->decline($request->user(), $pet, $transfer);

        return redirect()
            ->route('pets.index')
            ->with('status', 'Pet ownership transfer declined.');
    }

    public function cancel(Request $request, Pet $pet, PetOwnershipTransfer $transfer, PetOwnershipTransferService $transfers): RedirectResponse
    {
        $transfers->cancel($request->user(), $pet, $transfer);

        return back()->with('status', 'Pet ownership transfer cancelled.');
    }
}
