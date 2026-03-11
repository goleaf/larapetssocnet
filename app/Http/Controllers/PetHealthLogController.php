<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePetHealthLogRequest;
use App\Models\Pet;
use App\Models\PetHealthLog;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Throwable;

class PetHealthLogController extends Controller
{
    public function index(Request $request, string $slug): View
    {
        $pet = $this->resolvePet($slug);
        $this->ensureOwner($pet, $request->user(), 404);

        $logsQuery = PetHealthLog::query()->where('pet_id', $pet->getKey());

        $logs = (clone $logsQuery)
            ->latest('logged_at')
            ->paginate(15)
            ->withQueryString();

        $upcomingLogs = (clone $logsQuery)
            ->whereNotNull('next_due_at')
            ->where('next_due_at', '>=', now()->startOfDay())
            ->orderBy('next_due_at')
            ->limit(10)
            ->get();

        $trendSeries = (clone $logsQuery)
            ->where('log_type', 'weight')
            ->whereNotNull('weight_kg')
            ->orderBy('logged_at')
            ->limit(30);

        if (Schema::hasColumn('pet_health_logs', 'title')) {
            $trendSeries->select(['logged_at', 'weight_kg', 'title']);
        } else {
            $trendSeries->select(['logged_at', 'weight_kg']);
        }

        $trendSeries = $trendSeries->get();

        return view('pets.health.index', [
            'pet' => $pet,
            'logs' => $logs,
            'upcomingLogs' => $upcomingLogs,
            'trendData' => $this->buildTrendData($trendSeries),
        ]);
    }

    public function create(Request $request, string $slug): View
    {
        $pet = $this->resolvePet($slug);
        $this->ensureOwner($pet, $request->user(), 404);

        return view('pets.health.create', [
            'pet' => $pet,
        ]);
    }

    public function store(StorePetHealthLogRequest $request, string $slug): RedirectResponse
    {
        $pet = $this->resolvePet($slug);
        $this->ensureOwner($pet, $request->user());

        $validated = $request->validated();
        $type = $this->normalizeType((string) $validated['type']);

        $payload = $this->filterToExistingColumns('pet_health_logs', [
            'pet_id' => $pet->getKey(),
            'logged_by_user_id' => $request->user()?->getAuthIdentifier(),
            'log_type' => $type,
            'title' => $validated['title'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'weight_kg' => $type === 'weight' ? ($validated['value'] ?? null) : null,
            'logged_at' => $validated['logged_at'],
            'next_due_at' => $validated['next_due_at'] ?? null,
        ]);

        PetHealthLog::query()->create($payload);

        return redirect()
            ->route('pets.health.index', $pet->slug ?? $pet->getKey())
            ->with('status', 'Health log saved.');
    }

    public function edit(Request $request, string $slug, string $healthLog): View
    {
        $pet = $this->resolvePet($slug);
        $this->ensureOwner($pet, $request->user(), 404);

        $log = $this->resolveHealthLog($pet, $healthLog);

        return view('pets.health.edit', [
            'pet' => $pet,
            'log' => $log,
        ]);
    }

    public function update(StorePetHealthLogRequest $request, string $slug, string $healthLog): RedirectResponse
    {
        $pet = $this->resolvePet($slug);
        $this->ensureOwner($pet, $request->user());

        $log = $this->resolveHealthLog($pet, $healthLog);
        $validated = $request->validated();
        $type = $this->normalizeType((string) $validated['type']);

        $payload = $this->filterToExistingColumns('pet_health_logs', [
            'log_type' => $type,
            'title' => $validated['title'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'weight_kg' => $type === 'weight' ? ($validated['value'] ?? null) : null,
            'logged_at' => $validated['logged_at'],
            'next_due_at' => $validated['next_due_at'] ?? null,
        ]);

        $log->update($payload);

        return redirect()
            ->route('pets.health.index', $pet->slug ?? $pet->getKey())
            ->with('status', 'Health log updated.');
    }

    public function destroy(Request $request, string $slug, string $healthLog): RedirectResponse
    {
        $pet = $this->resolvePet($slug);
        $this->ensureOwner($pet, $request->user());

        $log = $this->resolveHealthLog($pet, $healthLog);
        $log->delete();

        return redirect()
            ->route('pets.health.index', $pet->slug ?? $pet->getKey())
            ->with('status', 'Health log deleted.');
    }

    protected function buildTrendData(Collection $series): array
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

    protected function resolvePet(string $slug): Pet
    {
        return Pet::query()
            ->where('slug', $slug)
            ->orWhere('id', $slug)
            ->firstOrFail();
    }

    protected function resolveHealthLog(Pet $pet, string $healthLog): PetHealthLog
    {
        return PetHealthLog::query()
            ->where('pet_id', $pet->getKey())
            ->whereKey($healthLog)
            ->firstOrFail();
    }

    protected function ensureOwner(Pet $pet, ?Authenticatable $user, int $status = 403): void
    {
        abort_unless($this->isOwner($pet, $user), $status);
    }

    protected function isOwner(Pet $pet, ?Authenticatable $user): bool
    {
        if (! $user) {
            return false;
        }

        $ownerId = data_get($pet, 'user_id') ?? data_get($pet, 'owner_id');

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
                ->only($columns)
                ->all();
        } catch (Throwable) {
            return $payload;
        }
    }

    protected function normalizeType(string $type): string
    {
        return $type === 'vaccine' ? 'vaccination' : $type;
    }
}
