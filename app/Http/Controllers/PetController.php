<?php

namespace App\Http\Controllers;

use App\Models\Pet;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        if (method_exists($pet, 'media')) {
            $gallery = $pet->media()->latest()->limit(24)->get();
        } elseif (method_exists($pet, 'galleryItems')) {
            $gallery = $pet->galleryItems()->latest()->limit(24)->get();
        }

        $healthLogs = collect();
        if ($isOwner && method_exists($pet, 'healthLogs')) {
            $healthLogs = $pet->healthLogs()->latest('logged_at')->limit(12)->get();
        }

        return view('pets.show', [
            'pet' => $pet,
            'tabs' => $tabs,
            'activeTab' => $activeTab,
            'isOwner' => $isOwner,
            'posts' => $posts,
            'gallery' => $gallery,
            'healthLogs' => $healthLogs,
        ]);
    }

    public function create(): View
    {
        return view('pets.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->petRules());

        $payload = $this->normalizePetPayload($validated, $request);

        $pet = Pet::query()->create($payload);

        return redirect()
            ->route('pets.show', $pet->slug ?? $pet->getKey())
            ->with('status', 'Pet profile created.');
    }

    public function edit(Request $request, string $slug): View
    {
        $pet = $this->resolvePet($slug);
        $this->ensureOwner($pet, $request->user());

        return view('pets.edit', [
            'pet' => $pet,
        ]);
    }

    public function update(Request $request, string $slug): RedirectResponse
    {
        $pet = $this->resolvePet($slug);
        $this->ensureOwner($pet, $request->user());

        $validated = $request->validate($this->petRules($pet));
        $payload = $this->normalizePetPayload($validated, $request);

        $pet->update($payload);

        return redirect()
            ->route('pets.show', $pet->slug ?? $pet->getKey())
            ->with('status', 'Pet profile updated.');
    }

    public function destroy(Request $request, string $slug): RedirectResponse
    {
        $pet = $this->resolvePet($slug);
        $this->ensureOwner($pet, $request->user());

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

        foreach (['species', 'breed', 'gender'] as $filterColumn) {
            $filterValue = trim((string) $request->string($filterColumn));

            if ($filterValue !== '' && $this->petTableHasColumn($filterColumn)) {
                $query->where($filterColumn, $filterValue);
            }
        }

        if ($request->filled('is_for_adoption') && $this->petTableHasColumn('is_for_adoption')) {
            $query->where('is_for_adoption', $request->boolean('is_for_adoption'));
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
                'gender' => (string) $request->string('gender'),
                'is_for_adoption' => $request->filled('is_for_adoption')
                    ? $request->boolean('is_for_adoption')
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

        if ($this->petTableHasColumn('is_for_adoption')) {
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

        foreach (['species', 'gender'] as $filterColumn) {
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
                'gender' => (string) $request->string('gender'),
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

    protected function petRules(?Pet $pet = null): array
    {
        $slugRule = ['nullable', 'string', 'max:180', 'alpha_dash'];

        if ($this->petTableHasColumn('slug')) {
            $slugRule[] = 'unique:pets,slug'.($pet ? ','.$pet->getKey() : '');
        }

        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => $slugRule,
            'species' => ['required', 'string', 'max:80'],
            'breed' => ['nullable', 'string', 'max:120'],
            'gender' => ['nullable', 'in:male,female,unknown'],
            'birthdate' => ['nullable', 'date', 'before_or_equal:today'],
            'weight' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'color' => ['nullable', 'string', 'max:80'],
            'bio' => ['nullable', 'string', 'max:5000'],
            'personality_tags' => ['nullable'],
            'is_public' => ['nullable', 'boolean'],
            'is_for_adoption' => ['nullable', 'boolean'],
        ];
    }

    protected function normalizePetPayload(array $validated, Request $request): array
    {
        $payload = $validated;

        $payload['slug'] = $payload['slug'] ?? Str::slug($payload['name'] ?? 'pet-'.Str::random(6));
        $payload['is_public'] = $request->boolean('is_public');
        $payload['is_for_adoption'] = $request->boolean('is_for_adoption');
        $payload['personality_tags'] = $this->normalizePersonalityTags($payload['personality_tags'] ?? null);

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
}
