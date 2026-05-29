<?php

namespace App\Services;

use App\Models\Pets\Pet;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use RuntimeException;

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
        $base = Str::of($petName)
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/u', '-')
            ->replaceMatches('/-+/', '-')
            ->trim('-')
            ->toString();

        if ($base === '') {
            $base = Str::of($ownerUsername)
                ->lower()
                ->replaceMatches('/[^a-z0-9]+/u', '-')
                ->replaceMatches('/-+/', '-')
                ->trim('-')
                ->toString() ?: 'pet';
        }

        if ($this->isReserved($base)) {
            $base .= '-pet';
        }

        return Str::limit($base, 93, '');
    }

    public function isReserved(string $slug): bool
    {
        return in_array($slug, $this->reserved, true);
    }

    public function generateUnique(string $petName, string $ownerUsername, ?int $ignoreId = null): string
    {
        $base = $this->normalize($petName, $ownerUsername);

        for ($attempt = 0; $attempt < 100; $attempt++) {
            $slug = Str::limit($base, 93, '').'-'.Str::lower(Str::random(6));

            if (! $this->slugExists($slug, $ignoreId)) {
                return $slug;
            }
        }

        throw new RuntimeException('Unable to generate a unique pet slug after 100 attempts.');
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
