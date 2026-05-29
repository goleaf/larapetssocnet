<?php

namespace App\Observers;

use App\Models\Identity\User;
use App\Models\Pets\Pet;
use App\Services\PetSlugService;

class PetObserver
{
    public function creating(Pet $pet): void
    {
        if (filled((string) $pet->slug) || blank((string) $pet->name)) {
            return;
        }

        $ownerUsername = $pet->relationLoaded('owner')
            ? ($pet->owner?->username)
            : null;

        if (! $ownerUsername && $pet->user_id) {
            $ownerUsername = User::query()
                ->whereKey($pet->user_id)
                ->value('username');
        }

        $pet->slug = app(PetSlugService::class)->generateUnique(
            (string) $pet->name,
            (string) ($ownerUsername ?? 'pet')
        );
    }
}
