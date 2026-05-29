<?php

namespace App\Http\Controllers\Pets;

use App\Http\Controllers\Controller;
use App\Models\Pets\Breed;
use App\Models\Pets\Species;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class BreedAutocompleteController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'species' => ['required', 'string', 'max:80'],
            'q' => ['nullable', 'string', 'max:80'],
        ]);

        $search = trim((string) ($validated['q'] ?? ''));
        $species = $this->resolveSpecies((string) $validated['species']);
        $normalizedSearch = $this->normalizeSearchName($search);

        $breeds = Breed::query()
            ->select(['id', 'name', 'slug', 'species_slug', 'species_id'])
            ->when(
                $species instanceof Species && Schema::hasColumn('breeds', 'species_id'),
                fn ($query) => $query->where('species_id', $species->getKey()),
                fn ($query) => $query->where('species_slug', strtolower((string) $validated['species']))
            )
            ->when(
                $normalizedSearch !== '' && Schema::hasColumn('breeds', 'normalized_name'),
                fn ($query) => $query->where('normalized_name', 'like', $normalizedSearch.'%')
            )
            ->when(
                $normalizedSearch !== '' && ! Schema::hasColumn('breeds', 'normalized_name'),
                fn ($query) => $query->where('name', 'like', $search.'%')
            )
            ->when(
                Schema::hasColumn('breeds', 'normalized_name'),
                fn ($query) => $query->orderBy('normalized_name')->orderBy('name'),
                fn ($query) => $query->orderBy('name')
            )
            ->limit(10)
            ->get()
            ->map(fn (Breed $breed): array => [
                'id' => $breed->getKey(),
                'name' => $breed->name,
                'slug' => $breed->slug,
                'species' => $breed->species_slug,
                'type' => 'breed',
            ])
            ->values();

        return response()->json([
            'data' => collect([
                [
                    'id' => null,
                    'name' => 'Mixed breed',
                    'slug' => 'mixed-breed',
                    'species' => $species?->slug ?? strtolower((string) $validated['species']),
                    'type' => 'mixed',
                ],
                [
                    'id' => null,
                    'name' => 'Unknown breed',
                    'slug' => 'unknown-breed',
                    'species' => $species?->slug ?? strtolower((string) $validated['species']),
                    'type' => 'unknown',
                ],
            ])->merge($breeds)->values(),
        ]);
    }

    private function resolveSpecies(string $value): ?Species
    {
        if (ctype_digit($value)) {
            return Species::query()->whereKey((int) $value)->first();
        }

        return Species::query()->where('slug', strtolower($value))->first();
    }

    private function normalizeSearchName(string $value): string
    {
        return Str::of($value)
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/u', '')
            ->toString();
    }
}
