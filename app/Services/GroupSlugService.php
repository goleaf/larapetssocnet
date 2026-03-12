<?php

namespace App\Services;

use App\Models\Group;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class GroupSlugService
{
    /**
     * @var list<string>
     */
    private array $reserved = [
        'create',
        'edit',
        'join',
        'leave',
        'requests',
        'members',
        'bans',
        'posts',
        'cover',
        'settings',
        'admin',
    ];

    public function normalize(string $value): string
    {
        $slug = Str::slug($value);

        return $slug !== '' ? $slug : 'group';
    }

    public function isReserved(string $slug): bool
    {
        return in_array($slug, $this->reserved, true);
    }

    public function generateUnique(string $seed, ?int $ignoreId = null): string
    {
        $base = $this->normalize($seed);

        if ($this->isReserved($base)) {
            $base = $base.'-group';
        }

        $slug = $base;
        $suffix = 1;

        while ($this->slugExists($slug, $ignoreId)) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    private function slugExists(string $slug, ?int $ignoreId): bool
    {
        return Group::query()
            ->withTrashed()
            ->where('slug', $slug)
            ->when($ignoreId !== null, fn (Builder $query): Builder => $query->whereKeyNot($ignoreId))
            ->exists();
    }
}
