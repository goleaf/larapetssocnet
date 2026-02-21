<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Post extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, SoftDeletes;

    public const VISIBILITY_PUBLIC = 'public';

    public const VISIBILITY_FOLLOWERS = 'followers';

    public const VISIBILITY_PRIVATE = 'private';

    protected $fillable = [
        'user_id',
        'pet_id',
        'body',
        'body_html',
        'type',
        'visibility',
        'location',
        'is_pinned',
        'likes_count',
        'comments_count',
        'shares_count',
    ];

    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    protected $appends = [
        'current_user_reaction',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photos')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif']);

        $this->addMediaCollection('videos')
            ->singleFile()
            ->acceptsMimeTypes(['video/mp4', 'video/quicktime', 'video/webm']);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->performOnCollections('photos')
            ->fit(Fit::Crop, 150, 150)
            ->format('webp')
            ->quality(80)
            ->nonQueued();

        $this->addMediaConversion('medium')
            ->performOnCollections('photos')
            ->width(800)
            ->format('webp')
            ->quality(85)
            ->nonQueued();

        $this->addMediaConversion('large')
            ->performOnCollections('photos')
            ->width(1200)
            ->format('webp')
            ->quality(90)
            ->nonQueued();
    }

    // Relationships

    public function author()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function pet()
    {
        return $this->belongsTo(Pet::class);
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    public function reactions()
    {
        return $this->morphMany(Reaction::class, 'reactable');
    }

    public function hashtags()
    {
        return $this->belongsToMany(Hashtag::class, 'post_hashtag');
    }

    public function postReactions()
    {
        return $this->hasMany(PostReaction::class);
    }

    public function savedBy()
    {
        return $this->belongsToMany(User::class, 'saved_posts');
    }

    // Scopes

    public function scopeVisibleTo(Builder $query, ?User $viewer)
    {
        $query->where(function (Builder $q) use ($viewer) {
            if (! $viewer) {
                // Guests see only public posts from public users
                $q->where('visibility', 'public')
                    ->whereHas('author', function ($a) {
                        $a->where('is_private', false)
                            ->where('is_banned', false);
                    });

                return;
            }

            // Authenticated user
            $q->where('user_id', $viewer->id)
                ->orWhere(function (Builder $q) use ($viewer) {
                    $blockedIds = $viewer->blocking()->pluck('blocked_id')
                        ->merge($viewer->blockedBy()->pluck('blocker_id'));

                    $q->whereNotIn('user_id', $blockedIds)
                        ->where(function ($q) use ($viewer) {
                            $q->where('visibility', 'public')
                                ->orWhere(function ($q) use ($viewer) {
                                    $q->where('visibility', 'followers')
                                        ->whereIn('user_id', $viewer->acceptedFollowing()->pluck('id'));
                                });
                        });
                });
        });
    }

    public function scopePublic(Builder $query)
    {
        $query->where('visibility', 'public');
    }

    public function scopeByType(Builder $query, string $type)
    {
        $query->where('type', $type);
    }

    public function scopeExplorable(Builder $query, ?User $viewer)
    {
        $query
            ->where('visibility', self::VISIBILITY_PUBLIC)
            ->whereHas('author', function (Builder $authorQuery): void {
                $authorQuery
                    ->where('is_private', false)
                    ->where('is_banned', false);
            });

        if ($viewer) {
            $blockedIds = $viewer->blocking()
                ->pluck('users.id')
                ->merge($viewer->blockedBy()->pluck('users.id'))
                ->unique();

            if ($blockedIds->isNotEmpty()) {
                $query->whereNotIn('user_id', $blockedIds);
            }
        }

        $query->whereNull('posts.deleted_at');
    }

    public function scopeTrending(Builder $query)
    {
        $query
            ->where('created_at', '>=', now()->subHours(48))
            ->where(function (Builder $scoreQuery): void {
                $scoreQuery
                    ->where('likes_count', '>', 0)
                    ->orWhere('comments_count', '>', 0);
            })
            // Approved exception: computed ordering has no Eloquent equivalent.
            ->orderByRaw('(likes_count + (comments_count * 2)) DESC, created_at DESC');
    }

    public function scopeTopRated(Builder $query)
    {
        $query
            ->orderByDesc('likes_count')
            ->orderByDesc('created_at');
    }

    public function scopeSearch(Builder $query, string $term)
    {
        $clean = Str::limit(trim($term), 100, '');

        $query->where(function (Builder $searchQuery) use ($clean): void {
            $searchQuery
                ->where('body', 'like', "%{$clean}%")
                ->orWhereHas('hashtags', fn (Builder $hashtagQuery) => $hashtagQuery->where('name', 'like', "%{$clean}%"))
                ->orWhere('location', 'like', "%{$clean}%");
        });
    }

    public function scopePinned(Builder $query)
    {
        $query->where('is_pinned', true);
    }

    public function scopeForFeed(Builder $query, User $user)
    {
        $followingIds = $user->acceptedFollowing()
            ->pluck('users.id')
            ->push($user->getKey())
            ->unique();

        $query
            ->whereIn('user_id', $followingIds)
            ->where(function (Builder $visibilityQuery) use ($user): void {
                $visibilityQuery
                    ->where('user_id', $user->getKey())
                    ->orWhereIn('visibility', [self::VISIBILITY_PUBLIC, self::VISIBILITY_FOLLOWERS]);
            })
            ->whereNull('posts.deleted_at');
    }

    // Accessors

    public function getExcerptAttribute(): string
    {
        return Str::limit(strip_tags($this->body_html ?? ''), 150);
    }

    public function getIsOwnedByAttribute(): bool
    {
        return auth()->check() && auth()->id() === $this->user_id;
    }

    public function getPhotoUrlsAttribute(): array
    {
        return $this->getMedia('photos')
            ->map(fn ($m) => [
                'thumb' => $m->getUrl('thumb'),
                'medium' => $m->getUrl('medium'),
                'large' => $m->getUrl('large'),
                'original' => $m->getUrl(),
            ])->toArray();
    }

    public function getVideoUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl('videos') ?: null;
    }

    public function refreshLikesCount(): void
    {
        $this->update(['likes_count' => $this->postReactions()->count()]);
    }

    public function refreshCommentsCount(): void
    {
        $this->update(['comments_count' => $this->comments()->count()]);
    }

    public function getHasMediaAttribute(): bool
    {
        return $this->hasMedia('photos') || $this->hasMedia('videos');
    }

    public function getCurrentUserReactionAttribute(): ?string
    {
        if (! auth()->check()) {
            return null;
        }

        // Avoid N+1 issues by checking if relation is loaded,
        // otherwise default to a direct query (or null if we're listing).
        if ($this->relationLoaded('postReactions')) {
            return $this->postReactions->firstWhere('user_id', auth()->id())?->type;
        }

        return $this->postReactions()->where('user_id', auth()->id())->value('type');
    }
}
