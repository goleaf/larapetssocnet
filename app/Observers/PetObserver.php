<?php

namespace App\Observers;

use App\Models\Pet;
use Illuminate\Support\Str;

class PetObserver
{
    public function creating(Pet $pet): void
    {
        if (filled((string) $pet->slug) || blank((string) $pet->name)) {
            return;
        }

        $baseSlug = Str::slug((string) $pet->name);
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
