<?php

namespace App\Http\Controllers\Pets;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pets\StorePetHealthLogRequest;
use App\Models\Pets\Pet;
use App\Models\Pets\PetHealthLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class PetHealthLogController extends Controller
{
    public function index(Request $request, Pet $pet): View
    {
        $this->authorize('update', $pet);

        $logs = PetHealthLog::paginateForPet($pet);
        $upcomingLogs = PetHealthLog::upcomingForPet($pet);
        $trendSeries = PetHealthLog::weightTrendForPet($pet);

        return view('pets.health.index', [
            'pet' => $pet,
            'logs' => $logs,
            'upcomingLogs' => $upcomingLogs,
            'trendData' => $this->buildTrendData($trendSeries),
        ]);
    }

    public function create(Request $request, Pet $pet): View
    {
        $this->authorize('update', $pet);

        return view('pets.health.create', [
            'pet' => $pet,
        ]);
    }

    public function store(StorePetHealthLogRequest $request, Pet $pet): RedirectResponse
    {
        $validated = $request->validated();
        $type = $this->normalizeType((string) $validated['type']);
        $nextDueAt = $this->resolveNextDueAt($request, $validated);

        $payload = $this->filterToExistingColumns('pet_health_logs', [
            'log_type' => $type,
            'title' => $validated['title'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'weight_kg' => $type === 'weight' ? ($validated['value'] ?? null) : null,
            'logged_at' => $validated['logged_at'],
            'next_due_at' => $nextDueAt,
        ]);

        PetHealthLog::createForPet($pet, $request->user(), $payload);

        return redirect()
            ->route('pets.health.index', $pet)
            ->with('status', 'Health log saved.');
    }

    public function edit(Request $request, Pet $pet, string $healthLog): View
    {
        $this->authorize('update', $pet);

        $log = $this->resolveHealthLog($pet, $healthLog);

        return view('pets.health.edit', [
            'pet' => $pet,
            'log' => $log,
        ]);
    }

    public function update(StorePetHealthLogRequest $request, Pet $pet, string $healthLog): RedirectResponse
    {
        $log = $this->resolveHealthLog($pet, $healthLog);
        $validated = $request->validated();
        $type = $this->normalizeType((string) $validated['type']);
        $nextDueAt = $this->resolveNextDueAt($request, $validated);

        $payload = $this->filterToExistingColumns('pet_health_logs', [
            'log_type' => $type,
            'title' => $validated['title'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'weight_kg' => $type === 'weight' ? ($validated['value'] ?? null) : null,
            'logged_at' => $validated['logged_at'],
            'next_due_at' => $nextDueAt,
        ]);

        $log->update($payload);

        return redirect()
            ->route('pets.health.index', $pet)
            ->with('status', 'Health log updated.');
    }

    public function destroy(Request $request, Pet $pet, string $healthLog): RedirectResponse
    {
        $this->authorize('update', $pet);

        $log = $this->resolveHealthLog($pet, $healthLog);
        $log->delete();

        return redirect()
            ->route('pets.health.index', $pet)
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

        $values = $series->pluck('weight_kg')->map(static fn ($value): float => (float) $value)->values();
        $min = $values->min();
        $max = $values->max();
        $range = max($max - $min, 0.01);
        $lastIndex = max($values->count() - 1, 1);

        $points = $values->map(function (float $value, int $index) use ($min, $range, $lastIndex, $series): array {
            $x = ($index / $lastIndex) * 100;
            $y = 100 - (($value - $min) / $range) * 100;

            $rawLabel = data_get($series[$index], 'logged_at');
            $label = null;

            if ($rawLabel instanceof CarbonInterface) {
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
            ->map(function (array $point, int $index): string {
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

    protected function resolveHealthLog(Pet $pet, string $healthLog): PetHealthLog
    {
        return PetHealthLog::findForPet($pet, $healthLog) ?? abort(404);
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

    protected function resolveNextDueAt(Request $request, array $validated): ?Carbon
    {
        if (filled($validated['next_due_at'] ?? null)) {
            return Carbon::parse((string) $validated['next_due_at']);
        }

        if (filled($validated['next_due_interval'] ?? null)) {
            try {
                $interval = $request->interval('next_due_interval');
            } catch (Throwable) {
                throw ValidationException::withMessages([
                    'next_due_interval' => 'Invalid interval format. Use ISO 8601 durations such as P7D.',
                ]);
            }

            if ($interval === null) {
                return null;
            }

            return Carbon::parse((string) $validated['logged_at'])->add($interval);
        }

        if (! filled($validated['next_due_in'] ?? null)) {
            return null;
        }

        $interval = $request->interval('next_due_in', (string) $validated['next_due_unit']);

        if ($interval === null) {
            return null;
        }

        return Carbon::parse((string) $validated['logged_at'])->add($interval);
    }
}
