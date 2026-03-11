<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePetRequest;
use App\Http\Requests\UpdatePetRequest;
use App\Models\Pet;
use App\Models\Post;
use App\Services\ChartService;
use App\Services\PetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PetController extends Controller
{
    public function __construct(
        private PetService $petService,
        private ChartService $chartService,
    ) {}

    public function show(Request $request, string $slug): View
    {
        $pet = $this->resolvePet($slug);
        $isOwner = $pet->isOwnedBy($request->user());

        $tabs = ['posts', 'gallery', 'health', 'about'];
        $activeTab = $request->string('tab')->toString() ?: 'posts';

        if (! in_array($activeTab, $tabs, true)) {
            $activeTab = 'posts';
        }

        if ($activeTab === 'health' && ! $isOwner) {
            $activeTab = 'posts';
        }

        $posts = $pet->recentPostsForShow();

        $myReactions = collect();
        $mySaved = collect();

        if ($request->user() && $posts->isNotEmpty()) {
            $postIds = $posts->modelKeys();
            $myReactions = Post::reactionMapForViewer($request->user(), $postIds);
            $mySaved = Post::savedMapForViewer($request->user(), $postIds);
        }

        $gallery = $pet->galleryForShow();

        $healthLogs = collect();
        $weightChartSvg = null;
        if ($isOwner) {
            $healthLogs = $pet->recentHealthLogs();
            $weightChartSvg = $this->chartService->weightChart($pet->weight_logs);
        }

        return view('pets.show', [
            'pet' => $pet,
            'tabs' => $tabs,
            'activeTab' => $activeTab,
            'isOwner' => $isOwner,
            'posts' => $posts,
            'myReactions' => $myReactions,
            'mySaved' => $mySaved,
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
        $search = trim((string) $request->string('q'));
        $sort = $request->string('sort')->toString() ?: 'newest';
        $isAdoptableFilter = ($request->filled('is_adoptable') || $request->filled('is_for_adoption'))
            ? ($request->boolean('is_adoptable') || $request->boolean('is_for_adoption'))
            : null;

        $pets = Pet::paginateExploreCatalog([
            'q' => $search,
            'species' => (string) $request->string('species'),
            'breed' => (string) $request->string('breed'),
            'sex' => (string) $request->string('sex'),
            'is_adoptable' => $isAdoptableFilter,
            'sort' => $sort,
        ]);

        return view('pets.explore', [
            'pets' => $pets,
            'filters' => [
                'q' => $search,
                'species' => (string) $request->string('species'),
                'breed' => (string) $request->string('breed'),
                'sex' => (string) $request->string('sex'),
                'is_adoptable' => $isAdoptableFilter,
                'sort' => $sort,
            ],
        ]);
    }

    public function adopt(Request $request): View
    {
        $search = trim((string) $request->string('q'));
        $sort = $request->string('sort')->toString() ?: 'newest';
        $pets = Pet::paginateAdoptionCatalog([
            'q' => $search,
            'species' => (string) $request->string('species'),
            'sex' => (string) $request->string('sex'),
            'sort' => $sort,
        ]);

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
        return Pet::resolveForRoute($slug) ?? abort(404);
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

    private function attachGalleryPhotos(Pet $pet, Request $request): void
    {
        foreach ((array) $request->file('gallery_photos', []) as $photo) {
            $pet->addMedia($photo)->toMediaCollection('gallery');
        }
    }
}
