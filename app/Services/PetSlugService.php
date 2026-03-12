<?php

namespace App\Services;

use App\Models\Pet;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class PetSlugService
{
    /**
     * @var list<string>
     */
    private array $reserved = [
        'create',
        'edit',
        'followers',
        'follow',
        'gallery',
        'health',
        'posts',
        'adoption',
        'avatar',
    ];

    public function normalize(string $petName, string $ownerUsername): string
    {
        $base = Str::slug(trim($petName.' '.$ownerUsername));

        if ($base === '') {
            $base = 'pet';
        }

        if ($this->isReserved($base)) {
            $base .= '-pet';
        }

        return Str::limit($base, 70, '');
    }

    public function isReserved(string $slug): bool
    {
        return in_array($slug, $this->reserved, true);
    }

    public function generateUnique(string $petName, string $ownerUsername, ?int $ignoreId = null): string
    {
        $base = $this->normalize($petName, $ownerUsername);
        $slug = $base;
        $suffix = 2;

        while ($this->slugExists($slug, $ignoreId)) {
            $slug = Str::limit($base.'-'.$suffix, 80, '');
            $suffix++;
        }

        return $slug;
    }

    private function slugExists(string $slug, ?int $ignoreId): bool
    {
        return Pet::query()
            ->withTrashed()
            ->where('slug', $slug)
            ->when($ignoreId !== null, fn (Builder $query): Builder => $query->whereKeyNot($ignoreId))
            ->exists();
    }
}
