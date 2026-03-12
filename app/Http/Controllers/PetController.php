<?php

namespace App\Http\Controllers;

use App\Actions\Pets\CreatePetAction;
use App\Actions\Pets\DeletePetAction;
use App\Actions\Pets\UpdatePetAction;
use App\Http\Requests\CreatePetRequest;
use App\Http\Requests\UpdatePetRequest;
use App\Models\Pet;
use App\Models\Post;
use App\Models\User;
use App\Services\ChartService;
use App\Services\PersonalityTagService;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PetController extends Controller
{
    public function __construct(private ChartService $chartService) {}

    public function index(Request $request): View
    {
        $pets = Pet::query()
            ->public()
            ->visibleTo($request->user())
            ->latest('created_at')
            ->cursorPaginate(12)
            ->withQueryString();

        return view('pets.index', [
            'pets' => $pets,
        ]);
    }

    public function show(Request $request, Pet $pet): View
    {
        $this->authorize('view', $pet);

        $pet->loadMissing(['user', 'species', 'breed', 'media', 'tags']);

        $isOwner = $pet->isOwnedBy($request->user());

        $tabs = ['posts', 'gallery', 'about'];

        if ($isOwner) {
            $tabs[] = 'health';
        }

        $activeTab = $request->string('tab')->toString() ?: 'posts';

        if (! in_array($activeTab, $tabs, true)) {
            $activeTab = 'posts';
        }

        $posts = $this->postsForShow($pet, $request->user());
        $gallery = $activeTab === 'gallery' ? $pet->galleryForShow() : collect();

        $healthLogs = collect();
        $weightChartSvg = null;

        if ($isOwner && $activeTab === 'health') {
            $healthLogs = $pet->recentHealthLogs();
            $weightChartSvg = $this->chartService->weightChart($pet->weight_logs);
        }

        $isFollowing = $request->user()?->isFollowingPet($pet) ?? false;
        $pet->setAttribute('viewer_is_following', $isFollowing);

        return view('pets.show', [
            'pet' => $pet,
            'tabs' => $tabs,
            'activeTab' => $activeTab,
            'isOwner' => $isOwner,
            'isFollowing' => $isFollowing,
            'posts' => $posts,
            'gallery' => $gallery,
            'healthLogs' => $healthLogs,
            'weightChartSvg' => $weightChartSvg,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Pet::class);

        return view('pets.create', $this->petFormDefaults());
    }

    public function store(CreatePetRequest $request, CreatePetAction $createPetAction): RedirectResponse
    {
        $this->authorize('create', Pet::class);

        $pet = $createPetAction->handle(
            $request->user(),
            $this->payloadFromRequest($request),
            $request->file('avatar'),
            (array) $request->file('gallery_photos', [])
        );

        return redirect()
            ->route('pets.show', $pet)
            ->with('status', __('pets.flash.created'));
    }

    public function edit(Pet $pet): View
    {
        $this->authorize('update', $pet);

        $pet->loadMissing('media');

        $galleryItems = collect($pet->getMedia(Pet::MEDIA_COLLECTION_GALLERY))
            ->sortBy(function ($media): string {
                $order = (int) ($media->order_column ?? 0);
                $timestamp = (int) (optional($media->created_at)->timestamp ?? 0);

                return sprintf('%05d-%010d', $order, $timestamp);
            })
            ->values();

        $galleryMax = (int) config('pets.gallery.max_photos', 30);

        return view('pets.edit', [
            'pet' => $pet,
            'galleryItems' => $galleryItems,
            'galleryMax' => $galleryMax,
            ...$this->petFormDefaults(),
        ]);
    }

    public function update(UpdatePetRequest $request, Pet $pet, UpdatePetAction $updatePetAction): RedirectResponse
    {
        $this->authorize('update', $pet);

        $pet = $updatePetAction->handle(
            $pet,
            $this->payloadFromRequest($request),
            $request->file('avatar'),
            (array) $request->file('gallery_photos', [])
        );

        return redirect()
            ->route('pets.show', $pet)
            ->with('status', __('pets.flash.updated'));
    }

    public function destroy(Request $request, Pet $pet, DeletePetAction $deletePetAction): RedirectResponse
    {
        $this->authorize('delete', $pet);

        $deletePetAction->handle($request->user(), $pet);

        return redirect()
            ->route('pets.index')
            ->with('status', __('pets.flash.deleted'));
    }

    public function explore(Request $request): View
    {
        $search = trim((string) $request->string('q'));
        $sort = $request->string('sort')->toString() ?: 'newest';
        $isAdoptableFilter = ($request->filled('is_adoptable') || $request->filled('is_for_adoption'))
            ? ($request->boolean('is_adoptable') || $request->boolean('is_for_adoption'))
            : null;
        $personalityTags = $this->normalizePersonalityTagsFilter($request->input('personality_tags'));

        $pets = Pet::paginateExploreCatalog([
            'q' => $search,
            'species' => (string) $request->string('species'),
            'breed' => (string) $request->string('breed'),
            'sex' => (string) $request->string('sex'),
            'personality_tags' => $personalityTags,
            'is_adoptable' => $isAdoptableFilter,
            'sort' => $sort,
        ], $request->user());

        return view('pets.explore', [
            'pets' => $pets,
            'filters' => [
                'q' => $search,
                'species' => (string) $request->string('species'),
                'breed' => (string) $request->string('breed'),
                'sex' => (string) $request->string('sex'),
                'personality_tags' => $personalityTags,
                'is_adoptable' => $isAdoptableFilter,
                'sort' => $sort,
            ],
            'personalityTagSuggestions' => app(PersonalityTagService::class)->getSuggestions(),
        ]);
    }

    public function adopt(Request $request): View
    {
        $search = trim((string) $request->string('q'));
        $sort = $request->string('sort')->toString() ?: 'newest';
        $personalityTags = $this->normalizePersonalityTagsFilter($request->input('personality_tags'));
        $pets = Pet::paginateAdoptionCatalog([
            'q' => $search,
            'species' => (string) $request->string('species'),
            'sex' => (string) $request->string('sex'),
            'personality_tags' => $personalityTags,
            'sort' => $sort,
        ], $request->user());

        return view('pets.adopt', [
            'pets' => $pets,
            'filters' => [
                'q' => $search,
                'species' => (string) $request->string('species'),
                'sex' => (string) $request->string('sex'),
                'personality_tags' => $personalityTags,
                'sort' => $sort,
            ],
            'personalityTagSuggestions' => app(PersonalityTagService::class)->getSuggestions(),
        ]);
    }

    /**
     * @return CursorPaginator<int, Post>
     */
    private function postsForShow(Pet $pet, ?User $viewer): CursorPaginator
    {
        return Post::query()
            ->select([
                'posts.id',
                'posts.user_id',
                'posts.pet_id',
                'posts.body',
                'posts.body_html',
                'posts.status',
                'posts.published_at',
                'posts.created_at',
            ])
            ->where('posts.pet_id', $pet->getKey())
            ->published()
            ->visibleTo($viewer)
            ->latest('posts.created_at')
            ->cursorPaginate(12)
            ->withQueryString();
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadFromRequest(Request $request): array
    {
        $validated = method_exists($request, 'validated') ? $request->validated() : [];

        return [
            'name' => $validated['name'] ?? null,
            'species' => $validated['species'] ?? null,
            'breed' => $validated['breed'] ?? null,
            'sex' => $validated['sex'] ?? ($validated['gender'] ?? 'unknown'),
            'gender' => $validated['gender'] ?? ($validated['sex'] ?? 'unknown'),
            'size' => $validated['size'] ?? null,
            'birthdate' => $validated['birth_date'] ?? ($validated['date_of_birth'] ?? null),
            'age_text' => $validated['age_text'] ?? null,
            'bio' => $validated['bio'] ?? null,
            'personality_tags' => $validated['personality_tags'] ?? null,
            'is_public' => $request->boolean('is_public'),
            'is_adoptable' => $request->boolean('is_adoptable') || $request->boolean('is_for_adoption'),
            'is_deceased' => $request->boolean('is_deceased'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function petFormDefaults(): array
    {
        $service = app(PersonalityTagService::class);

        return [
            'personalityTagSuggestions' => $service->getSuggestions(),
            'personalityTagMax' => $service->maxTags(),
        ];
    }

    /**
     * @return list<string>
     */
    private function normalizePersonalityTagsFilter(mixed $rawTags): array
    {
        if ($rawTags === null || $rawTags === '') {
            return [];
        }

        return app(PersonalityTagService::class)->normalize($rawTags);
    }
}
