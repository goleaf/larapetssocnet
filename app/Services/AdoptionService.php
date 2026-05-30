<?php

namespace App\Services;

use App\Models\Identity\User;
use App\Models\Marketplace\MarketplaceListing;
use App\Models\Pets\Pet;
use App\Support\Search\SearchInput;
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
            $updates = [
                'adoption_status' => $status,
                'is_adoptable' => in_array($status, ['available', 'pending'], true),
            ];

            if ($status === 'available') {
                $updates['adoption_listed_at'] = now();
                $updates['adoption_fee'] = $data['fee'] ?? null;
                $updates['adoption_notes'] = $data['notes'] ?? null;
                $updates['adoption_contact'] = $data['contact'] ?? null;
            }

            $pet->update($updates);
            $this->syncMarketplaceListing($pet->fresh(), $status, $data);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncMarketplaceListing(Pet $pet, string $status, array $data): void
    {
        $location = User::query()
            ->whereKey($pet->user_id)
            ->value('location');

        $listing = MarketplaceListing::query()
            ->withTrashed()
            ->where('pet_id', $pet->getKey())
            ->where('listing_type', 'adoption')
            ->latest('id')
            ->first();

        if ($status === 'available') {
            $attributes = [
                'user_id' => $pet->user_id,
                'pet_id' => $pet->getKey(),
                'title' => $pet->name.' is available for adoption',
                'description' => (string) ($data['notes'] ?? $pet->adoption_notes ?? $pet->bio ?? 'Contact the owner for adoption details.'),
                'price' => $data['fee'] ?? $pet->adoption_fee,
                'currency' => 'USD',
                'listing_type' => 'adoption',
                'status' => MarketplaceListing::STATUS_ACTIVE,
                'location_text' => is_string($location) && $location !== '' ? $location : null,
                'contact_email' => filter_var($data['contact'] ?? $pet->adoption_contact, FILTER_VALIDATE_EMAIL)
                    ? (string) ($data['contact'] ?? $pet->adoption_contact)
                    : null,
            ];

            if ($listing instanceof MarketplaceListing) {
                if ($listing->trashed()) {
                    $listing->restore();
                }

                $listing->update($attributes);

                return;
            }

            MarketplaceListing::query()->create($attributes);

            return;
        }

        if (! $listing instanceof MarketplaceListing) {
            return;
        }

        $listing->update([
            'status' => $status === 'adopted'
                ? MarketplaceListing::STATUS_SOLD
                : MarketplaceListing::STATUS_ARCHIVED,
        ]);

        if ($listing->trashed()) {
            $listing->restore();
        }
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
            ->when($this->searchPattern($filters['location'] ?? null), fn ($q, string $pattern) => $q->whereHas('owner', fn ($o) => $o->where('location', 'like', $pattern)))
            ->latest('adoption_listed_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    private function searchPattern(mixed $value): ?string
    {
        $search = SearchInput::normalize($value);

        return SearchInput::hasSearchableLength($search) ? SearchInput::containsPattern($search) : null;
    }
}
