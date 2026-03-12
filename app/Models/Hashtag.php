<?php

namespace App\Models;

use App\Support\Hashtags\HashtagNormalizer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Hashtag extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'normalized_name',
        'posts_count',
    ];

    protected function casts(): array
    {
        return [
            'posts_count' => 'integer',
        ];
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
        $query->where(function (Builder $searchQuery) use ($term): void {
            $searchQuery
                ->where('name', 'like', "%{$term}%")
                ->orWhere('normalized_name', 'like', "%{$term}%");
        });
    }

    public function scopePopular(Builder $query): Builder
    {
        return $query
            ->select(['hashtags.*'])
            ->orderByDesc('hashtags.posts_count');
    }

    public function scopeForType(Builder $query, string $morphType): Builder
    {
        return $query
            ->select(['hashtags.*'])
            ->whereHas('posts', fn (Builder $postQuery) => $postQuery->where('posts.type', $morphType));
    }

    public function scopeBySlug(Builder $query, string $slug): Builder
    {
        $normalizer = new HashtagNormalizer;
        $normalized = $normalizer->normalizeFromSlug($slug);

        if (! $normalized) {
            return $query->whereKey(-1);
        }

        return $query->where('normalized_name', $normalized);
    }

    public function scopeSearchResultColumns(Builder $query): Builder
    {
        return $query->select([
            'hashtags.id',
            'hashtags.name',
            'hashtags.slug',
            'hashtags.normalized_name',
            'hashtags.posts_count',
            'hashtags.created_at',
        ]);
    }

    public static function paginateSearchResults(string $term, int $perPage = 15): LengthAwarePaginator
    {
        $normalizer = new HashtagNormalizer;
        $normalized = $normalizer->normalizeFromInput($term);
        $searchTerm = $normalized ?? $term;

        return self::query()
            ->searchResultColumns()
            ->when($searchTerm !== '', fn (Builder $query) => $query->search($searchTerm))
            ->latest('hashtags.created_at')
            ->paginate($perPage)
            ->withQueryString();
    }
}
