<?php

namespace App\Services;

use App\Models\Identity\User;
use App\Models\Pets\PetCareTip;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PetCareTipService
{
    public function submit(User $user, array $data): PetCareTip
    {
        return PetCareTip::create([
            'user_id' => $user->id,
            'title' => $data['title'],
            'content' => $data['content'] ?? $data['body'] ?? '',
            'species' => $data['species'] ?? null,
            'is_approved' => false,
            'helpful_count' => 0,
        ]);
    }

    public function approve(PetCareTip $tip): void
    {
        $tip->update(['is_approved' => true]);
    }

    public function reject(PetCareTip $tip): void
    {
        $tip->update(['is_approved' => false]);
    }

    public function vote(User $user, PetCareTip $tip): void
    {
        // Simple increment — in a full implementation you'd check for duplicates via a pivot table.
        $tip->increment('helpful_count');
    }

    public function getListing(?string $species = null, int $perPage = 15): LengthAwarePaginator
    {
        return PetCareTip::approved()
            ->bySpecies($species)
            ->with('author')
            ->orderByDesc('helpful_count')
            ->paginate($perPage);
    }
}
