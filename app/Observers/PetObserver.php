<?php

namespace App\Observers;

use App\Models\Pet;
use App\Models\User;
use Illuminate\Support\Str;

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

        $seed = trim((string) $pet->name.' '.(string) $ownerUsername);
        $baseSlug = Str::slug($seed);
        $baseSlug = $baseSlug !== '' ? $baseSlug : 'pet';
        $slug = $baseSlug;
        $suffix = 2;

        while (Pet::query()->withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$suffix}";
            $suffix++;
        }

        $pet->slug = $slug;
    }
}
