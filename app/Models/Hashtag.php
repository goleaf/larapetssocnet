<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Hashtag extends Model
{
    use HasFactory;
    use HasSlug;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'posts_count',
    ];

    /**
     * @var list<string>
     */
    protected $appends = [
        'display_name',
    ];

    protected function casts(): array
    {
        return [
            'posts_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $hashtag): void {
            $hashtag->name = Str::lower(trim((string) $hashtag->name));
        });
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug');
    }

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'post_hashtag', 'hashtag_id', 'post_id')
            ->withTimestamps();
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        return $query->where(function (Builder $subQuery) use ($term): void {
            $subQuery
                ->where('name', 'like', "%{$term}%")
                ->orWhere('slug', 'like', "%{$term}%");
        });
    }

    public function scopeTrending(Builder $query, int $limit = 20): Builder
    {
        return $query->orderByDesc('posts_count')->limit($limit);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected function displayName(): Attribute
    {
        return Attribute::get(fn (): string => '#'.$this->name);
    }
}
