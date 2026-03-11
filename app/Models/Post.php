<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
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
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Post extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, SoftDeletes;

    public const TYPE_TEXT = 'text';

    public const TYPE_PHOTO = 'photo';

    public const TYPE_VIDEO = 'video';

    public const VISIBILITY_PUBLIC = 'public';

    public const VISIBILITY_FOLLOWERS = 'followers';

    public const VISIBILITY_PRIVATE = 'private';

    protected $fillable = [
        'user_id',
        'group_id',
        'pet_id',
        'body',
        'body_html',
        'type',
        'visibility',
        'location',
        'tagged_pets',
        'is_pinned',
        'likes_count',
        'comments_count',
        'shares_count',
    ];

    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
            'tagged_pets' => 'array',
            'group_id' => 'integer',
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
            ->acceptsMimeTypes(['video/mp4', 'video/quicktime', 'video/webm', 'application/octet-stream', 'application/x-empty']);
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

    public function likes(): HasMany
    {
        return $this->hasMany(Like::class);
    }

    public function postMedia(): HasMany
    {
        return $this->hasMany(PostMedia::class)->orderBy('order');
    }

    public function reactions(): MorphMany
    {
        return $this->morphMany(Reaction::class, 'reactable');
    }

    public function hashtags(): BelongsToMany
    {
        return $this->belongsToMany(Hashtag::class, 'post_hashtag');
    }

    public function postReactions(): HasMany
    {
        return $this->hasMany(PostReaction::class);
    }

    public function savedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'saved_posts');
    }

    // Scopes

    public function scopeVisibleTo(Builder $query, ?User $viewer): void
    {
        if ($viewer?->hasAnyRole(['admin', 'moderator'])) {
            $query->whereHas('author', function (Builder $authorQuery): void {
                $authorQuery->where('is_banned', false);
            });

            return;
        }

        if (! $viewer) {
            $query->where('visibility', self::VISIBILITY_PUBLIC)
                ->whereHas('author', function (Builder $authorQuery): void {
                    $authorQuery
                        ->where('is_private', false)
                        ->where('is_banned', false);
                });

            return;
        }

        $blockedIds = $viewer->blocking()
            ->pluck('blocked_id')
            ->merge($viewer->blockedBy()->pluck('blocker_id'))
            ->unique()
            ->values();

        $followingIds = $viewer->acceptedFollowing()
            ->pluck('users.id')
            ->unique()
            ->values();

        $query->where(function (Builder $visibilityQuery) use ($viewer, $blockedIds, $followingIds): void {
            $visibilityQuery
                ->where('user_id', $viewer->getKey())
                ->orWhere(function (Builder $otherPostsQuery) use ($viewer, $blockedIds, $followingIds): void {
                    $otherPostsQuery
                        ->where('user_id', '!=', $viewer->getKey())
                        ->when($blockedIds->isNotEmpty(), function (Builder $blockedQuery) use ($blockedIds): void {
                            $blockedQuery->whereNotIn('user_id', $blockedIds);
                        })
                        ->whereHas('author', function (Builder $authorQuery): void {
                            $authorQuery->where('is_banned', false);
                        })
                        ->where(function (Builder $rulesQuery) use ($followingIds): void {
                            $rulesQuery
                                ->where(function (Builder $publicFromPublicAccounts): void {
                                    $publicFromPublicAccounts
                                        ->where('visibility', self::VISIBILITY_PUBLIC)
                                        ->whereHas('author', function (Builder $authorQuery): void {
                                            $authorQuery->where('is_private', false);
                                        });
                                })
                                ->orWhere(function (Builder $followersOnlyQuery) use ($followingIds): void {
                                    $followersOnlyQuery
                                        ->where('visibility', self::VISIBILITY_FOLLOWERS)
                                        ->whereIn('user_id', $followingIds);
                                })
                                ->orWhere(function (Builder $publicFromPrivateAccounts) use ($followingIds): void {
                                    $publicFromPrivateAccounts
                                        ->where('visibility', self::VISIBILITY_PUBLIC)
                                        ->whereHas('author', function (Builder $authorQuery): void {
                                            $authorQuery->where('is_private', true);
                                        })
                                        ->whereIn('user_id', $followingIds);
                                });
                        });
                });
        });
    }

    public function scopePublic(Builder $query)
    {
        $query->where('visibility', 'public');
    }

    public function scopePublished(Builder $query): void
    {
        $query->where('status', 'published');
    }

    public function scopeNotBlockedFor(Builder $query, ?User $viewer): void
    {
        if (! $viewer) {
            return;
        }

        $blockedIds = $viewer->blocking()
            ->pluck('users.id')
            ->merge($viewer->blockedBy()->pluck('users.id'))
            ->unique()
            ->values();

        if ($blockedIds->isNotEmpty()) {
            $query->whereNotIn('user_id', $blockedIds);
        }
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

    public function scopeSearchResultColumns(Builder $query): Builder
    {
        return $query->select([
            'posts.id',
            'posts.user_id',
            'posts.body',
            'posts.location',
            'posts.status',
            'posts.visibility',
            'posts.created_at',
            'posts.is_pinned',
        ]);
    }

    public static function paginateSearchResults(?User $viewer, string $term, int $perPage = 15): LengthAwarePaginator
    {
        return self::query()
            ->searchResultColumns()
            ->published()
            ->visibleTo($viewer)
            ->search($term)
            ->latest('posts.created_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function scopeProfileTimelineColumns(Builder $query): Builder
    {
        return $query->select([
            'posts.id',
            'posts.user_id',
            'posts.body',
            'posts.body_html',
            'posts.status',
            'posts.visibility',
            'posts.location',
            'posts.is_pinned',
            'posts.likes_count',
            'posts.comments_count',
            'posts.shares_count',
            'posts.created_at',
        ]);
    }

    public function scopeForProfile(Builder $query, User $user): Builder
    {
        return $query->where('posts.user_id', $user->getKey());
    }

    public static function paginateProfileTimeline(User $profileOwner, ?User $viewer, int $perPage = 10): LengthAwarePaginator
    {
        return self::query()
            ->profileTimelineColumns()
            ->forProfile($profileOwner)
            ->with(['user', 'hashtags'])
            ->published()
            ->visibleTo($viewer)
            ->where('posts.visibility', '!=', self::VISIBILITY_PRIVATE)
            ->orderByDesc('posts.is_pinned')
            ->latest('posts.created_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @return Collection<int, self>
     */
    public static function recentPrivateForProfileOwner(User $profileOwner, int $limit = 10): Collection
    {
        return self::query()
            ->profileTimelineColumns()
            ->forProfile($profileOwner)
            ->where('posts.visibility', self::VISIBILITY_PRIVATE)
            ->with(['user', 'hashtags'])
            ->latest('posts.created_at')
            ->limit($limit)
            ->get();
    }

    public static function privateCountForProfile(User $profileOwner): int
    {
        return (int) self::query()
            ->forProfile($profileOwner)
            ->where('posts.visibility', self::VISIBILITY_PRIVATE)
            ->count();
    }

    /**
     * @return list<array{month: string, count: int}>
     */
    public static function monthlyActivitySummaryForUser(User $user, int $months = 6): array
    {
        $safeMonths = max(1, $months);
        $startMonth = CarbonImmutable::now()->startOfMonth()->subMonths($safeMonths - 1);
        $countsByMonth = [];

        self::query()
            ->select(['posts.id', 'posts.created_at'])
            ->where('posts.user_id', $user->getKey())
            ->where('posts.created_at', '>=', $startMonth)
            ->orderBy('posts.created_at')
            ->cursor()
            ->each(function (self $post) use (&$countsByMonth): void {
                if (! $post->created_at) {
                    return;
                }

                $monthKey = $post->created_at->format('Y-m');
                $countsByMonth[$monthKey] = ($countsByMonth[$monthKey] ?? 0) + 1;
            });

        $summary = [];

        for ($index = 0; $index < $safeMonths; $index++) {
            $month = $startMonth->addMonths($index);
            $monthKey = $month->format('Y-m');

            $summary[] = [
                'month' => $month->format('M'),
                'count' => (int) ($countsByMonth[$monthKey] ?? 0),
            ];
        }

        return $summary;
    }

    public function scopePinned(Builder $query)
    {
        $query->where('is_pinned', true);
    }

    public function scopeForGroup(Builder $query, int $groupId): Builder
    {
        return $query->where('group_id', $groupId);
    }

    public function scopeForFeed(Builder $query, User $user): Builder
    {
        $followingIds = $user->acceptedFollowing()
            ->pluck('users.id')
            ->unique()
            ->values();

        return $query
            ->where(function (Builder $feedQuery) use ($user, $followingIds): void {
                $feedQuery
                    ->where('user_id', $user->getKey())
                    ->orWhere(function (Builder $followingQuery) use ($followingIds): void {
                        $followingQuery
                            ->when(
                                $followingIds->isNotEmpty(),
                                fn (Builder $scopedQuery) => $scopedQuery->whereIn('user_id', $followingIds),
                                fn (Builder $scopedQuery) => $scopedQuery->whereRaw('1 = 0')
                            )
                            ->whereIn('visibility', [self::VISIBILITY_PUBLIC, self::VISIBILITY_FOLLOWERS]);
                    });
            })
            ->whereHas('author', function (Builder $authorQuery): void {
                $authorQuery->where('is_banned', false);
            })
            ->whereNull('posts.group_id')
            ->whereNull('posts.deleted_at')
            ->notBlockedFor($user);
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

    public function isLikedBy(User $user): bool
    {
        if ($this->relationLoaded('likes')) {
            return $this->likes->contains(function (Like $like) use ($user): bool {
                return $like->user_id === $user->getKey();
            });
        }

        return $this->likes()->where('user_id', $user->getKey())->exists();
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

    public function canBeViewedBy(?User $viewer): bool
    {
        return app(\App\Services\VisibilityService::class)->canView($viewer, $this);
    }
}
