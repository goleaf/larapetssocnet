<?php

namespace App\Models\Pets;

use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable([
    'user_id',
    'title',
    'slug',
    'species',
    'category',
    'content',
    'is_approved',
    'helpful_count',
])]
class PetCareTip extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_approved' => 'boolean',
            'helpful_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $tip): void {
            if (! $tip->slug && $tip->title) {
                $tip->slug = static::generateUniqueSlug((string) $tip->title);
            }
        });
    }

    public static function generateUniqueSlug(string $seed): string
    {
        $base = Str::slug($seed) ?: 'tip';
        $slug = $base;
        $suffix = 1;

        while (static::query()->where('slug', $slug)->exists()) {
            $suffix++;
            $slug = "{$base}-{$suffix}";
        }

        return $slug;
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('is_approved', true);
    }

    public function scopeBySpecies(Builder $query, ?string $species): Builder
    {
        if (! $species) {
            return $query;
        }

        return $query->where('species', $species);
    }
}
