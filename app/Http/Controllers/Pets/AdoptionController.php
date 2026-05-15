<?php

namespace App\Http\Controllers\Pets;

use App\Http\Controllers\Controller;
use App\Models\Pets\Pet;
use App\Services\AdoptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdoptionController extends Controller
{
    public function __construct(
        private AdoptionService $adoptionService,
    ) {}

    /**
     * Public adoption browse page.
     */
    public function index(Request $request): View
    {
        $listings = $this->adoptionService->getListings(
            filters: $request->only(['species', 'size', 'free', 'location']),
            viewer: $request->user(),
        );

        return view('pets.adoption.index', [
            'listings' => $listings,
            'species' => Pet::SPECIES,
            'sizes' => Pet::SIZES,
            'filters' => $request->only(['species', 'size', 'free', 'location']),
        ]);
    }

    /**
     * Update adoption status for a pet (owner only).
     */
    public function update(Request $request, Pet $pet): JsonResponse
    {
        $this->authorize('update', $pet);

        $request->validate([
            'status' => ['required', Rule::in(array_keys(AdoptionService::TRANSITIONS))],
            'fee' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
            'contact' => ['nullable', 'string', 'max:150'],
        ]);

        $this->adoptionService->setStatus(
            $pet,
            $request->input('status'),
            $request->only(['fee', 'notes', 'contact']),
        );

        return response()->json([
            'success' => true,
            'status' => $pet->fresh()->adoption_status,
        ]);
    }
}
