<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePetRequest;
use App\Http\Requests\UpdatePetRequest;
use App\Models\Pet;
use App\Services\ChartService;
use App\Services\PetService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Throwable;

class PetController extends Controller
{
    public function __construct(
        private PetService $petService,
        private ChartService $chartService,
    ) {}

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
        $weightChartSvg = null;
        if ($isOwner && method_exists($pet, 'healthLogs')) {
            $healthLogs = $pet->healthLogs()->latest('logged_at')->limit(12)->get();
            $weightChartSvg = $this->chartService->weightChart($pet->weight_logs);
        }

        return view('pets.show', [
            'pet' => $pet,
            'tabs' => $tabs,
            'activeTab' => $activeTab,
            'isOwner' => $isOwner,
            'posts' => $posts,
            'gallery' => $gallery,
            'healthLogs' => $healthLogs,
            'weightChartSvg' => $weightChartSvg,
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
        $avatar = $request->file('avatar');

        $data = $this->prepareData($validated, $request);
        $pet = $this->petService->create($request->user(), $data, $avatar);

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
        $avatar = $request->file('avatar');

        $data = $this->prepareData($validated, $request);
        $this->petService->update($pet, $data, $avatar);

        $this->attachGalleryPhotos($pet, $request);

        return redirect()
            ->route('pets.show', $pet->slug ?? $pet->getKey())
            ->with('status', 'Pet profile updated.');
    }

    public function destroy(Request $request, string $slug): RedirectResponse
    {
        $pet = $this->resolvePet($slug);
        $this->authorize('delete', $pet);

        $this->petService->delete($pet);

        return redirect()
            ->route('pets.explore')
            ->with('status', 'Pet profile deleted.');
    }

    public function explore(Request $request): View
    {
        $query = Pet::query()->with('owner:id,name');

        if ($this->petTableHasColumn('is_public')) {
            $query->where('is_public', true);
        }

        $search = trim((string) $request->string('q'));
        if ($search !== '') {
            $query->where(function ($innerQuery) use ($search) {
                foreach (['name', 'bio', 'breed', 'species'] as $col) {
                    if ($this->petTableHasColumn($col)) {
                        $innerQuery->orWhere($col, 'like', "%{$search}%");
                    }
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
        $query = Pet::query()->with('owner:id,name');

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
                foreach (['name', 'bio', 'breed'] as $col) {
                    if ($this->petTableHasColumn($col)) {
                        $innerQuery->orWhere($col, 'like', "%{$search}%");
                    }
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

    /**
     * Map request data to the shape PetService expects.
     *
     * @return array<string, mixed>
     */
    private function prepareData(array $validated, Request $request): array
    {
        return [
            'name' => $validated['name'] ?? null,
            'species' => $validated['species'] ?? null,
            'breed' => $validated['breed'] ?? null,
            'gender' => $validated['gender'] ?? ($validated['sex'] ?? 'unknown'),
            'size' => $validated['size'] ?? null,
            'date_of_birth' => $validated['date_of_birth'] ?? ($validated['birth_date'] ?? null),
            'age_text' => $validated['age_text'] ?? null,
            'bio' => $validated['bio'] ?? null,
            'personality_tags' => $this->normalizePersonalityTags($validated['personality_tags'] ?? null),
            'is_public' => $request->boolean('is_public'),
            'is_adoptable' => $request->boolean('is_adoptable') || $request->boolean('is_for_adoption'),
            'is_deceased' => $request->boolean('is_deceased'),
        ];
    }

    protected function resolvePet(string $slug): Pet
    {
        return Pet::query()
            ->when($this->petTableHasColumn('slug'), fn ($query) => $query->where('slug', $slug))
            ->orWhere('id', $slug)
            ->firstOrFail();
    }

    protected function isOwner(Pet $pet, ?Authenticatable $user): bool
    {
        if (! $user) {
            return false;
        }

        $ownerId = data_get($pet, 'user_id') ?? data_get($pet, 'owner_id');

        return (int) $ownerId === (int) $user->getAuthIdentifier();
    }

    private function normalizePersonalityTags(mixed $rawTags): array
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

    private function petTableHasColumn(string $column): bool
    {
        try {
            return Schema::hasColumn('pets', $column);
        } catch (Throwable) {
            return false;
        }
    }

    private function attachGalleryPhotos(Pet $pet, Request $request): void
    {
        foreach ((array) $request->file('gallery_photos', []) as $photo) {
            $pet->addMedia($photo)->toMediaCollection('gallery');
        }
    }
}
