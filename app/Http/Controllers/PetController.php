<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePetRequest;
use App\Http\Requests\UpdatePetRequest;
use App\Models\Pet;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class PetController extends Controller
{
    public function show(Request $request, string $slug): View
    {
        $pet = $this->resolvePet($slug);
        $isOwner = $this->isOwner($pet, $request->user());

        $tabs = ['posts', 'gallery', 'health', 'about'];
        $activeTab = $request->string('tab')->toString() ?: 'posts';

        if (! in_array($activeTab, $tabs, true)) {
            $activeTab = 'posts';
        }

        if ($activeTab === 'health' && ! $isOwner) {
            $activeTab = 'posts';
        }

        $posts = collect();
        if (method_exists($pet, 'posts')) {
            $posts = $pet->posts()->latest()->limit(12)->get();
        }

        $gallery = collect();
        if (method_exists($pet, 'getMedia')) {
            $gallery = collect($pet->getMedia('gallery'))
                ->sortByDesc(fn ($media) => $media->created_at)
                ->take(24)
                ->values();
        } elseif (method_exists($pet, 'galleryItems')) {
            $gallery = $pet->galleryItems()->latest()->limit(24)->get();
        }

        $healthLogs = collect();
        $weightTrendData = [
            'path' => null,
            'points' => [],
            'min' => null,
            'max' => null,
        ];
        if ($isOwner && method_exists($pet, 'healthLogs')) {
            $healthLogs = $pet->healthLogs()->latest('logged_at')->limit(12)->get();

            $weightSeries = $pet->healthLogs()
                ->where('log_type', 'weight')
                ->whereNotNull('weight_kg')
                ->orderBy('logged_at')
                ->limit(30)
                ->select(['logged_at', 'weight_kg'])
                ->get();

            $weightTrendData = $this->buildWeightTrendData($weightSeries);
        }

        return view('pets.show', [
            'pet' => $pet,
            'tabs' => $tabs,
            'activeTab' => $activeTab,
            'isOwner' => $isOwner,
            'posts' => $posts,
            'gallery' => $gallery,
            'healthLogs' => $healthLogs,
            'weightTrendData' => $weightTrendData,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Pet::class);

        return view('pets.create');
    }

    public function store(StorePetRequest $request): RedirectResponse
    {
        $this->authorize('create', Pet::class);

        $validated = $request->validated();

        $payload = $this->normalizePetPayload($validated, $request);

        $pet = Pet::query()->create($payload);
        $this->attachGalleryPhotos($pet, $request);

        return redirect()
            ->route('pets.show', $pet->slug ?? $pet->getKey())
            ->with('status', 'Pet profile created.');
    }

    public function edit(Request $request, string $slug): View
    {
        $pet = $this->resolvePet($slug);
        $this->authorize('update', $pet);

        return view('pets.edit', [
            'pet' => $pet,
        ]);
    }

    public function update(UpdatePetRequest $request, string $slug): RedirectResponse
    {
        $pet = $this->resolvePet($slug);
        $this->authorize('update', $pet);

        $validated = $request->validated();
        $payload = $this->normalizePetPayload($validated, $request);

        $pet->update($payload);
        $this->attachGalleryPhotos($pet, $request);

        return redirect()
            ->route('pets.show', $pet->slug ?? $pet->getKey())
            ->with('status', 'Pet profile updated.');
    }

    public function destroy(Request $request, string $slug): RedirectResponse
    {
        $pet = $this->resolvePet($slug);
        $this->authorize('delete', $pet);

        $pet->delete();

        return redirect()
            ->route('pets.explore')
            ->with('status', 'Pet profile deleted.');
    }

    public function explore(Request $request): View
    {
        $query = Pet::query();

        if ($this->petTableHasColumn('is_public')) {
            $query->where('is_public', true);
        }

        $search = trim((string) $request->string('q'));
        if ($search !== '') {
            $query->where(function ($innerQuery) use ($search) {
                if ($this->petTableHasColumn('name')) {
                    $innerQuery->orWhere('name', 'like', "%{$search}%");
                }

                if ($this->petTableHasColumn('bio')) {
                    $innerQuery->orWhere('bio', 'like', "%{$search}%");
                }

                if ($this->petTableHasColumn('breed')) {
                    $innerQuery->orWhere('breed', 'like', "%{$search}%");
                }

                if ($this->petTableHasColumn('species')) {
                    $innerQuery->orWhere('species', 'like', "%{$search}%");
                }
            });
        }

        foreach (['species', 'breed', 'sex'] as $filterColumn) {
            $filterValue = trim((string) $request->string($filterColumn));

            if ($filterValue !== '' && $this->petTableHasColumn($filterColumn)) {
                $query->where($filterColumn, $filterValue);
            }
        }

        if ($request->filled('is_adoptable') || $request->filled('is_for_adoption')) {
            $adoptableFilterValue = $request->boolean('is_adoptable') || $request->boolean('is_for_adoption');
            if ($this->petTableHasColumn('is_adoptable')) {
                $query->where('is_adoptable', $adoptableFilterValue);
            } elseif ($this->petTableHasColumn('is_for_adoption')) {
                $query->where('is_for_adoption', $adoptableFilterValue);
            }
        }

        $sort = $request->string('sort')->toString() ?: 'newest';

        match ($sort) {
            'oldest' => $query->oldest('created_at'),
            'name_asc' => $query->orderBy('name'),
            'name_desc' => $query->orderByDesc('name'),
            'weight_desc' => $query->orderByDesc('weight'),
            default => $query->latest('created_at'),
        };

        $pets = $query->paginate(12)->withQueryString();

        return view('pets.explore', [
            'pets' => $pets,
            'filters' => [
                'q' => $search,
                'species' => (string) $request->string('species'),
                'breed' => (string) $request->string('breed'),
                'sex' => (string) $request->string('sex'),
                'is_adoptable' => ($request->filled('is_adoptable') || $request->filled('is_for_adoption'))
                    ? ($request->boolean('is_adoptable') || $request->boolean('is_for_adoption'))
                    : null,
                'sort' => $sort,
            ],
        ]);
    }

    public function adopt(Request $request): View
    {
        $query = Pet::query();

        if ($this->petTableHasColumn('is_public')) {
            $query->where('is_public', true);
        }

        if ($this->petTableHasColumn('is_adoptable')) {
            $query->where('is_adoptable', true);
        } elseif ($this->petTableHasColumn('is_for_adoption')) {
            $query->where('is_for_adoption', true);
        }

        $search = trim((string) $request->string('q'));
        if ($search !== '') {
            $query->where(function ($innerQuery) use ($search) {
                if ($this->petTableHasColumn('name')) {
                    $innerQuery->orWhere('name', 'like', "%{$search}%");
                }

                if ($this->petTableHasColumn('bio')) {
                    $innerQuery->orWhere('bio', 'like', "%{$search}%");
                }

                if ($this->petTableHasColumn('breed')) {
                    $innerQuery->orWhere('breed', 'like', "%{$search}%");
                }
            });
        }

        foreach (['species', 'sex'] as $filterColumn) {
            $filterValue = trim((string) $request->string($filterColumn));

            if ($filterValue !== '' && $this->petTableHasColumn($filterColumn)) {
                $query->where($filterColumn, $filterValue);
            }
        }

        $sort = $request->string('sort')->toString() ?: 'newest';

        match ($sort) {
            'oldest' => $query->oldest('created_at'),
            'name_asc' => $query->orderBy('name'),
            'name_desc' => $query->orderByDesc('name'),
            default => $query->latest('created_at'),
        };

        $pets = $query->paginate(12)->withQueryString();

        return view('pets.adopt', [
            'pets' => $pets,
            'filters' => [
                'q' => $search,
                'species' => (string) $request->string('species'),
                'sex' => (string) $request->string('sex'),
                'sort' => $sort,
            ],
        ]);
    }

    protected function resolvePet(string $slug): Pet
    {
        return Pet::query()
            ->where('slug', $slug)
            ->orWhere('id', $slug)
            ->firstOrFail();
    }

    protected function ensureOwner(Pet $pet, ?Authenticatable $user): void
    {
        abort_unless($this->isOwner($pet, $user), 403);
    }

    protected function isOwner(Pet $pet, ?Authenticatable $user): bool
    {
        if (! $user) {
            return false;
        }

        $ownerId = data_get($pet, 'user_id') ?? data_get($pet, 'owner_id');

        return (int) $ownerId === (int) $user->getAuthIdentifier();
    }

    protected function normalizePetPayload(array $validated, Request $request): array
    {
        $payload = [
            'name' => $validated['name'] ?? null,
            'species' => $validated['species'] ?? null,
            'breed' => $validated['breed'] ?? null,
            'sex' => $validated['sex'] ?? ($validated['gender'] ?? null),
            'birth_date' => $validated['birth_date'] ?? ($validated['birthdate'] ?? null),
            'bio' => $validated['bio'] ?? null,
            'personality_tags' => $this->normalizePersonalityTags($validated['personality_tags'] ?? null),
            'is_public' => $request->boolean('is_public'),
            'is_adoptable' => $request->boolean('is_adoptable') || $request->boolean('is_for_adoption'),
        ];

        if ($this->petTableHasColumn('slug')) {
            $payload['slug'] = $validated['slug'] ?? Str::slug($payload['name'] ?? 'pet-'.Str::random(6));
        }

        $payload['is_public'] = $request->boolean('is_public');
        $payload['is_adoptable'] = $request->boolean('is_adoptable') || $request->boolean('is_for_adoption');

        if ($ownerColumn = $this->resolvePetOwnerColumn()) {
            $payload[$ownerColumn] = $request->user()?->getAuthIdentifier();
        }

        return $this->filterToExistingColumns('pets', $payload);
    }

    protected function normalizePersonalityTags(mixed $rawTags): array
    {
        if (is_array($rawTags)) {
            return collect($rawTags)
                ->map(static fn ($tag) => trim((string) $tag))
                ->filter()
                ->values()
                ->all();
        }

        if (! is_string($rawTags) || trim($rawTags) === '') {
            return [];
        }

        return collect(explode(',', $rawTags))
            ->map(static fn ($tag) => trim($tag))
            ->filter()
            ->values()
            ->all();
    }

    protected function resolvePetOwnerColumn(): ?string
    {
        foreach (['user_id', 'owner_id'] as $column) {
            if ($this->petTableHasColumn($column)) {
                return $column;
            }
        }

        return null;
    }

    protected function filterToExistingColumns(string $table, array $payload): array
    {
        try {
            $columns = Schema::getColumnListing($table);

            if ($columns === []) {
                return $payload;
            }

            return collect($payload)
                ->only($columns)
                ->all();
        } catch (Throwable) {
            return $payload;
        }
    }

    protected function petTableHasColumn(string $column): bool
    {
        try {
            return Schema::hasColumn('pets', $column);
        } catch (Throwable) {
            return false;
        }
    }

    protected function attachGalleryPhotos(Pet $pet, Request $request): void
    {
        foreach ((array) $request->file('gallery_photos', []) as $photo) {
            $pet->addMedia($photo)->toMediaCollection('gallery');
        }
    }

    protected function buildWeightTrendData(Collection $series): array
    {
        if ($series->isEmpty()) {
            return [
                'path' => null,
                'points' => [],
                'min' => null,
                'max' => null,
            ];
        }

        $values = $series->pluck('weight_kg')->map(static fn ($value) => (float) $value)->values();
        $min = $values->min();
        $max = $values->max();
        $range = max($max - $min, 0.01);
        $lastIndex = max($values->count() - 1, 1);

        $points = $values->map(function (float $value, int $index) use ($min, $range, $lastIndex, $series) {
            $x = ($index / $lastIndex) * 100;
            $y = 100 - (($value - $min) / $range) * 100;

            $rawLabel = data_get($series[$index], 'logged_at');
            $label = null;

            if ($rawLabel instanceof \Illuminate\Support\CarbonInterface) {
                $label = $rawLabel->format('M j');
            } elseif (is_string($rawLabel) && $rawLabel !== '') {
                try {
                    $label = Carbon::parse($rawLabel)->format('M j');
                } catch (Throwable) {
                    $label = $rawLabel;
                }
            }

            return [
                'x' => round($x, 2),
                'y' => round($y, 2),
                'label' => $label,
                'value' => $value,
            ];
        })->all();

        $path = collect($points)
            ->map(function (array $point, int $index) {
                $command = $index === 0 ? 'M' : 'L';

                return sprintf('%s %s %s', $command, $point['x'], $point['y']);
            })
            ->implode(' ');

        return [
            'path' => $path,
            'points' => $points,
            'min' => $min,
            'max' => $max,
        ];
    }
}
