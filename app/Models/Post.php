<?php

namespace App\Models;

use App\Traits\HasCounterCache;
use App\Traits\HasReactions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Post extends Model implements HasMedia
{
    use HasCounterCache;
    use HasFactory;
    use HasReactions;
    use InteractsWithMedia;
    use SoftDeletes;

    public const VISIBILITY_PUBLIC = 'public';
    public const VISIBILITY_FOLLOWERS = 'followers';
    public const VISIBILITY_PRIVATE = 'private';

    public const TYPE_TEXT = 'text';
    public const TYPE_PHOTO = 'photo';
    public const TYPE_VIDEO = 'video';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'pet_id',
        'group_id',
        'body',
        'body_html',
        'visibility',
        'type',
        'status',
        'location',
        'tagged_pets',
        'metadata',
        'published_at',
        'is_pinned',
        'likes_count',
        'comments_count',
        'reactions_count',
        'shares_count',
    ];

    /**
     * @var list<string>
     */
    protected $appends = [
        'cover_photo_url',
        'excerpt',
        'photo_urls',
        'video_url',
        'has_media',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'tagged_pets' => 'array',
            'published_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
            'is_pinned' => 'boolean',
            'likes_count' => 'integer',
            'comments_count' => 'integer',
            'reactions_count' => 'integer',
            'shares_count' => 'integer',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photos')
            ->useDisk('public')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif']);

        $this->addMediaCollection('videos')
            ->useDisk('public')
            ->singleFile();

        // legacy compatibility
        $this->addMediaCollection('video')->singleFile();
        $this->addMediaCollection('images');
    }

    public function registerMediaConversions(?\Spatie\MediaLibrary\MediaCollections\Models\Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->fit(Fit::Crop, 150, 150)
            ->format('webp')
            ->quality(80)
            ->performOnCollections('photos')
            ->nonQueued();

        $this->addMediaConversion('medium')
            ->width(800)
            ->format('webp')
            ->quality(85)
            ->performOnCollections('photos')
            ->nonQueued();

        $this->addMediaConversion('large')
            ->width(1200)
            ->format('webp')
            ->quality(90)
            ->performOnCollections('photos')
            ->nonQueued();
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function user(): BelongsTo
    {
        return $this->author();
    }

    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function topLevelComments(): HasMany
    {
        return $this->comments()->whereNull('parent_id');
    }

    public function postReactions(): HasMany
    {
        return $this->hasMany(PostReaction::class);
    }

    public function saves(): HasMany
    {
        return $this->hasMany(SavedPost::class);
    }

    public function postReports(): HasMany
    {
        return $this->hasMany(PostReport::class);
    }

    public function hashtags(): BelongsToMany
    {
        return $this->belongsToMany(Hashtag::class, 'post_hashtag', 'post_id', 'hashtag_id')
            ->withTimestamps();
    }

    public function savedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'saved_posts', 'post_id', 'user_id')->withTimestamps();
    }

    public function reactions(): MorphMany
    {
        return $this->morphMany(Reaction::class, 'reactable');
    }

    public function reports(): MorphMany
    {
        return $this->morphMany(Report::class, 'reportable');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where(function (Builder $subQuery): void {
            $subQuery->whereNull('status')->orWhere('status', 'published');
        })->where(function (Builder $subQuery): void {
            $subQuery->whereNull('published_at')->orWhere('published_at', '<=', now());
        });
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query->where('visibility', self::VISIBILITY_PUBLIC);
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopePinned(Builder $query): Builder
    {
        return $query->where('is_pinned', true);
    }

    public function scopeNotBlockedFor(Builder $query, ?User $viewer): Builder
    {
        if (! $viewer || ! User::hasBlocksTable()) {
            return $query;
        }

        $blockedIds = collect();

        if (User::hasBlocksTable()) {
            $blockedIds = $viewer->blocking()->pluck('users.id')
                ->merge($viewer->blockedBy()->pluck('users.id'))
                ->unique();
        }

        if ($blockedIds->isEmpty()) {
            return $query;
        }

        return $query->whereNotIn('user_id', $blockedIds);
    }

    public function scopeVisibleTo(Builder $query, ?User $viewer): Builder
    {
        $query->published();

        if (! $viewer) {
            return $query->where('visibility', self::VISIBILITY_PUBLIC)
                ->whereHas('author', fn (Builder $author) => $author->where('is_private', false)->where('is_banned', false));
        }

        $acceptedFollowingIds = $viewer->acceptedFollowing()->pluck('users.id');
        $blockedIds = collect();

        if (User::hasBlocksTable()) {
            $blockedIds = $viewer->blocking()->pluck('users.id')
                ->merge($viewer->blockedBy()->pluck('users.id'))
                ->unique();
        }

        return $query->where(function (Builder $visibilityQuery) use ($viewer, $acceptedFollowingIds, $blockedIds): void {
            $visibilityQuery
                ->where('user_id', $viewer->id)
                ->orWhere(function (Builder $publicOrFollowers) use ($acceptedFollowingIds, $blockedIds): void {
                    if ($blockedIds->isNotEmpty()) {
                        $publicOrFollowers->whereNotIn('user_id', $blockedIds);
                    }

                    $publicOrFollowers->where(function (Builder $allowed) use ($acceptedFollowingIds): void {
                        $allowed->where('visibility', self::VISIBILITY_PUBLIC)
                            ->orWhere(function (Builder $followers) use ($acceptedFollowingIds): void {
                                $followers->where('visibility', self::VISIBILITY_FOLLOWERS)
                                    ->whereIn('user_id', $acceptedFollowingIds);
                            });
                    });
                });
        });
    }

    public function scopeForFeed(Builder $query, User $user): Builder
    {
        $followingIds = $user->acceptedFollowing()->pluck('users.id');

        return $query->visibleTo($user)
            ->where(function (Builder $feed) use ($user, $followingIds): void {
                $feed->where('user_id', $user->id)
                    ->orWhereIn('user_id', $followingIds);
            });
    }

    public function canBeViewedBy(?User $viewer): bool
    {
        if (! $viewer) {
            return $this->visibility === self::VISIBILITY_PUBLIC;
        }

        if ($viewer->id === $this->user_id) {
            return true;
        }

        if ($viewer->hasBlockingRelationshipWith($this->author)) {
            return false;
        }

        return match ($this->visibility) {
            self::VISIBILITY_PRIVATE => false,
            self::VISIBILITY_FOLLOWERS => $viewer->isFollowing($this->author),
            default => true,
        };
    }

    protected function coverPhotoUrl(): Attribute
    {
        return Attribute::get(function (): string {
            return $this->getFirstMediaUrl('photos', 'medium')
                ?: $this->getFirstMediaUrl('photos')
                ?: $this->getFirstMediaUrl('videos')
                ?: $this->getFirstMediaUrl('video')
                ?: '';
        });
    }

    protected function excerpt(): Attribute
    {
        return Attribute::get(fn (): string => Str::limit(strip_tags((string) ($this->body_html ?? $this->body)), 150));
    }

    protected function photoUrls(): Attribute
    {
        return Attribute::get(fn (): array => $this->getMedia('photos')
            ->map(fn ($m): array => [
                'thumb' => $m->getUrl('thumb'),
                'medium' => $m->getUrl('medium'),
                'large' => $m->getUrl('large'),
                'original' => $m->getUrl(),
            ])->all());
    }

    protected function videoUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->getFirstMediaUrl('videos') ?: $this->getFirstMediaUrl('video') ?: null);
    }

    public function getHasMediaAttribute(): bool
    {
        return $this->hasMedia('photos') || $this->hasMedia('videos') || $this->hasMedia('video');
    }

    public function refreshLikesCount(): void
    {
        $count = $this->postReactions()->count();
        $this->updateQuietly(['likes_count' => $count, 'reactions_count' => $count]);
    }

    public function refreshCommentsCount(): void
    {
        $this->updateQuietly(['comments_count' => $this->comments()->count()]);
    }

    /**
     * @return Collection<int, string>
     */
    public static function visibilityOptions(): Collection
    {
        return collect([self::VISIBILITY_PUBLIC, self::VISIBILITY_FOLLOWERS, self::VISIBILITY_PRIVATE]);
    }
}
