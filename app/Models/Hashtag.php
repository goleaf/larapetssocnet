<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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

    public function posts()
    {
        return $this->belongsToMany(Post::class, 'post_hashtag');
    }

    // Scopes

    public function scopeTrending(Builder $query, int $limit = 20)
    {
        $query->orderByDesc('posts_count')->limit($limit);
    }

    public function scopeSearch(Builder $query, string $term)
    {
        $query->where('name', 'like', "%{$term}%");
    }
}
