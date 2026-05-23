<?php

namespace App\Http\Controllers\Pets;

use App\Http\Controllers\Controller;
use App\Models\Pets\Breed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BreedAutocompleteController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'species' => ['required', 'string', 'max:80'],
            'q' => ['nullable', 'string', 'max:80'],
        ]);

        $search = trim((string) ($validated['q'] ?? ''));

        $breeds = Breed::query()
            ->select(['id', 'name', 'slug', 'species_slug'])
            ->where('species_slug', strtolower((string) $validated['species']))
            ->when($search !== '', fn ($query) => $query->where('name', 'like', $search.'%'))
            ->orderBy('name')
            ->limit(12)
            ->get()
            ->map(fn (Breed $breed): array => [
                'id' => $breed->getKey(),
                'name' => $breed->name,
                'slug' => $breed->slug,
                'species' => $breed->species_slug,
            ])
            ->values();

        return response()->json([
            'data' => $breeds,
        ]);
    }
}
