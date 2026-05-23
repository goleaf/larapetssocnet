<?php

namespace App\Http\Controllers\Pets;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pets\StorePetMilestoneRequest;
use App\Models\Pets\Pet;
use App\Models\Pets\PetMilestone;
use App\Services\PetMilestoneService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PetMilestoneController extends Controller
{
    public function store(StorePetMilestoneRequest $request, Pet $pet, PetMilestoneService $milestones): RedirectResponse
    {
        $milestones->create($pet, $request->user(), $request->validated());

        return redirect()
            ->route('pets.show', ['pet' => $pet, 'tab' => 'milestones'])
            ->with('status', 'Milestone added.');
    }

    public function update(StorePetMilestoneRequest $request, Pet $pet, PetMilestone $milestone, PetMilestoneService $milestones): RedirectResponse
    {
        abort_unless((int) $milestone->pet_id === (int) $pet->getKey(), 404);

        $milestones->update($milestone, $request->validated());

        return redirect()
            ->route('pets.show', ['pet' => $pet, 'tab' => 'milestones'])
            ->with('status', 'Milestone updated.');
    }

    public function destroy(Request $request, Pet $pet, PetMilestone $milestone): RedirectResponse
    {
        $this->authorize('manageMilestones', $pet);

        abort_unless((int) $milestone->pet_id === (int) $pet->getKey(), 404);

        $milestone->delete();

        return redirect()
            ->route('pets.show', ['pet' => $pet, 'tab' => 'milestones'])
            ->with('status', 'Milestone deleted.');
    }
}
