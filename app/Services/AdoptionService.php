<?php

namespace App\Services;

use App\Models\Identity\User;
use App\Models\Pets\Pet;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AdoptionService
{
    /** @var array<string, list<string>> */
    public const TRANSITIONS = [
        'not_listed' => ['available'],
        'available' => ['pending', 'not_listed'],
        'pending' => ['available', 'adopted'],
        'adopted' => ['not_listed'],
    ];

    public function setStatus(Pet $pet, string $status, array $data = []): void
    {
        $allowed = self::TRANSITIONS[$pet->adoption_status] ?? [];

        if (! in_array($status, $allowed, true)) {
            throw new RuntimeException(
                "Invalid adoption transition: {$pet->adoption_status} → {$status}"
            );
        }

        DB::transaction(function () use ($pet, $status, $data): void {
            $updates = ['adoption_status' => $status];

            if ($status === 'available') {
                $updates['adoption_listed_at'] = now();
                $updates['adoption_fee'] = $data['fee'] ?? null;
                $updates['adoption_notes'] = $data['notes'] ?? null;
                $updates['adoption_contact'] = $data['contact'] ?? null;
            }

            $pet->update($updates);
        });
    }

    public function getListings(array $filters = [], ?User $viewer = null, int $perPage = 20): LengthAwarePaginator
    {
        return Pet::availableForAdoption()
            ->public()
            ->visibleTo($viewer)
            ->with(['owner', 'media'])
            ->when($filters['species'] ?? null, fn ($q, $s) => $q->bySpecies($s))
            ->when($filters['size'] ?? null, fn ($q, $s) => $q->where('size', $s))
            ->when($filters['free'] ?? false, fn ($q) => $q->where(function ($q): void {
                $q->whereNull('adoption_fee')->orWhere('adoption_fee', 0);
            }))
            ->when($filters['location'] ?? null, fn ($q, $l) => $q->whereHas('owner', fn ($o) => $o->where('location', 'like', "%{$l}%")))
            ->latest('adoption_listed_at')
            ->paginate($perPage)
            ->withQueryString();
    }
}
