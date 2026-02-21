<?php

namespace App\Http\Controllers;

use App\Models\PetCareTip;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Throwable;

class PetCareTipController extends Controller
{
    public function index(Request $request): View
    {
        $query = PetCareTip::query();
        $userId = $request->user()?->getAuthIdentifier();

        $search = trim((string) $request->string('q'));
        if ($search !== '') {
            $query->where(function ($innerQuery) use ($search) {
                $innerQuery
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%");
            });
        }

        $species = trim((string) $request->string('species'));
        if ($species !== '') {
            $query->where('species', $species);
        }

        $query->where(function ($approvalQuery) use ($userId) {
            $approvalQuery->where('is_approved', true);

            if ($userId !== null) {
                $approvalQuery
                    ->orWhere('user_id', $userId)
                    ->orWhere('owner_id', $userId);
            }
        });

        $sort = $request->string('sort')->toString() ?: 'latest';

        match ($sort) {
            'helpful' => $query->orderByDesc('helpful_count'),
            'oldest' => $query->oldest('created_at'),
            default => $query->latest('created_at'),
        };

        return view('tips.index', [
            'tips' => $query->paginate(12)->withQueryString(),
            'filters' => [
                'q' => $search,
                'species' => $species,
                'sort' => $sort,
            ],
        ]);
    }

    public function show(Request $request, string $tip): View
    {
        $tipModel = $this->resolveTip($tip);

        abort_unless($this->isVisibleTo($tipModel, $request->user()), 404);

        return view('tips.show', [
            'tip' => $tipModel,
            'isOwner' => $this->isOwner($tipModel, $request->user()),
        ]);
    }

    public function create(): View
    {
        return view('tips.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());

        $payload = $this->filterToExistingColumns('pet_care_tips', [
            'title' => $validated['title'],
            'content' => $validated['content'],
            'species' => $validated['species'] ?? null,
            'category' => $validated['category'] ?? null,
            'is_approved' => false,
            $this->resolveOwnerColumn() => $request->user()?->getAuthIdentifier(),
        ]);

        $tip = PetCareTip::query()->create($payload);

        return redirect()
            ->route('tips.show', $tip->slug ?? $tip->getKey())
            ->with('status', 'Tip submitted. It may require approval before being public.');
    }

    public function edit(Request $request, string $tip): View
    {
        $tipModel = $this->resolveTip($tip);

        $this->authorizeUnapprovedOwnerAction($tipModel, $request->user());

        return view('tips.edit', [
            'tip' => $tipModel,
        ]);
    }

    public function update(Request $request, string $tip): RedirectResponse
    {
        $tipModel = $this->resolveTip($tip);

        $this->authorizeUnapprovedOwnerAction($tipModel, $request->user());

        $validated = $request->validate($this->rules());

        $payload = $this->filterToExistingColumns('pet_care_tips', [
            'title' => $validated['title'],
            'content' => $validated['content'],
            'species' => $validated['species'] ?? null,
            'category' => $validated['category'] ?? null,
        ]);

        $tipModel->update($payload);

        return redirect()
            ->route('tips.show', $tipModel->slug ?? $tipModel->getKey())
            ->with('status', 'Tip updated.');
    }

    public function destroy(Request $request, string $tip): RedirectResponse
    {
        $tipModel = $this->resolveTip($tip);

        $this->authorizeUnapprovedOwnerAction($tipModel, $request->user());

        $tipModel->delete();

        return redirect()
            ->route('tips.index')
            ->with('status', 'Tip deleted.');
    }

    public function helpful(Request $request, string $tip): RedirectResponse|JsonResponse
    {
        $tipModel = $this->resolveTip($tip);

        abort_unless($this->isVisibleTo($tipModel, $request->user()), 404);

        $column = $this->resolveHelpfulCountColumn();
        if ($column) {
            $tipModel->increment($column);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'helpful_count' => (int) ($column ? $tipModel->fresh()?->{$column} : 0),
            ]);
        }

        return back()->with('status', 'Thanks for your feedback.');
    }

    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:180'],
            'content' => ['required', 'string', 'max:10000'],
            'species' => ['nullable', 'string', 'max:80'],
            'category' => ['nullable', 'string', 'max:80'],
        ];
    }

    protected function resolveTip(string $tip): PetCareTip
    {
        return PetCareTip::query()
            ->where('slug', $tip)
            ->orWhere('id', $tip)
            ->firstOrFail();
    }

    protected function resolveOwnerColumn(): string
    {
        if ($this->tipsTableHasColumn('user_id')) {
            return 'user_id';
        }

        if ($this->tipsTableHasColumn('owner_id')) {
            return 'owner_id';
        }

        return 'user_id';
    }

    protected function resolveHelpfulCountColumn(): ?string
    {
        foreach (['helpful_count', 'likes_count'] as $column) {
            if ($this->tipsTableHasColumn($column)) {
                return $column;
            }
        }

        return null;
    }

    protected function isVisibleTo(PetCareTip $tip, ?Authenticatable $user): bool
    {
        if ((bool) data_get($tip, 'is_approved', true)) {
            return true;
        }

        return $this->isOwner($tip, $user);
    }

    protected function authorizeUnapprovedOwnerAction(PetCareTip $tip, ?Authenticatable $user): void
    {
        abort_unless($this->isOwner($tip, $user), 403);
        abort_if((bool) data_get($tip, 'is_approved', false), 403);
    }

    protected function isOwner(PetCareTip $tip, ?Authenticatable $user): bool
    {
        if (! $user) {
            return false;
        }

        $ownerId = data_get($tip, 'user_id') ?? data_get($tip, 'owner_id');

        return (int) $ownerId === (int) $user->getAuthIdentifier();
    }

    protected function filterToExistingColumns(string $table, array $payload): array
    {
        try {
            $columns = Schema::getColumnListing($table);

            if ($columns === []) {
                return $payload;
            }

            return collect($payload)
                ->reject(static fn ($value, $key) => $key === '')
                ->only($columns)
                ->all();
        } catch (Throwable) {
            return collect($payload)
                ->reject(static fn ($value, $key) => $key === '')
                ->all();
        }
    }

    protected function tipsTableHasColumn(string $column): bool
    {
        try {
            return Schema::hasColumn('pet_care_tips', $column);
        } catch (Throwable) {
            return false;
        }
    }
}
