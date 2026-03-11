<?php

namespace App\Models;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Hashtag extends Model
{
    use HasFactory, HasSlug;

    protected $fillable = [
        'name',
        'slug',
        'posts_count',
    ];

    /**
     * Get the options for generating the slug.
     */
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug');
    }

    // Relationships

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'post_hashtag');
    }

    // Scopes

    public function scopeTrending(Builder $query, int $limit = 20): void
    {
        $query->orderByDesc('posts_count')->limit($limit);
    }

    public function scopeSearch(Builder $query, string $term): void
    {
        $query->where('name', 'like', "%{$term}%");
    }

    public function scopePopular(Builder $query): Builder
    {
        return $query
            ->select(['hashtags.*'])
            ->orderByDesc('hashtags.posts_count');
    }

    public function scopeForType(Builder $query, string $postType): Builder
    {
        return $query
            ->select(['hashtags.*'])
            ->whereHas('posts', fn (Builder $postQuery) => $postQuery->where('posts.type', $postType));
    }

    public function scopeSearchResultColumns(Builder $query): Builder
    {
        return $query->select([
            'hashtags.id',
            'hashtags.name',
            'hashtags.slug',
            'hashtags.posts_count',
            'hashtags.created_at',
        ]);
    }

    public static function paginateSearchResults(string $term, int $perPage = 15): LengthAwarePaginator
    {
        return self::query()
            ->searchResultColumns()
            ->when($term !== '', fn (Builder $query) => $query->search($term))
            ->latest('hashtags.created_at')
            ->paginate($perPage)
            ->withQueryString();
    }
}
