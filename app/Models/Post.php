<?php

namespace App\Models;

use App\Enums\PostStatus;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\CursorPaginator;
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
        'status',
        'published_at',
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
            'status' => PostStatus::class,
            'published_at' => 'datetime',
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

    public function tags(): BelongsToMany
    {
        return $this->hashtags();
    }

    public function postReactions(): HasMany
    {
        return $this->hasMany(PostReaction::class);
    }

    public function savedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'saved_posts');
    }

    public function sharedGroups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'group_posts', 'post_id', 'group_id')
            ->withPivot(['added_by_user_id'])
            ->withTimestamps();
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

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->select(['posts.*'])
            ->where('posts.status', PostStatus::Published->value);
    }

    public function scopeNotBlockedFor(Builder $query, ?User $viewer): void
    {
        if (! $viewer) {
            return;
        }

        $query
            ->whereNotIn('posts.user_id', $viewer->blocking()->select('users.id'))
            ->whereNotIn('posts.user_id', $viewer->blockedBy()->select('users.id'));
    }

    public function scopeByType(Builder $query, string $type)
    {
        $query->where('type', $type);
    }

    public function scopeByPet(Builder $query, int|string $petId): Builder
    {
        return $query->select([
            'posts.id',
            'posts.user_id',
            'posts.group_id',
            'posts.pet_id',
            'posts.body',
            'posts.body_html',
            'posts.type',
            'posts.visibility',
            'posts.status',
            'posts.location',
            'posts.tagged_pets',
            'posts.metadata',
            'posts.is_pinned',
            'posts.likes_count',
            'posts.comments_count',
            'posts.reactions_count',
            'posts.shares_count',
            'posts.published_at',
            'posts.created_at',
            'posts.updated_at',
            'posts.deleted_at',
        ])->where('posts.pet_id', (int) $petId);
    }

    public function scopeWithMedia(Builder $query): Builder
    {
        return $query->select([
            'posts.id',
            'posts.user_id',
            'posts.group_id',
            'posts.pet_id',
            'posts.body',
            'posts.body_html',
            'posts.type',
            'posts.visibility',
            'posts.status',
            'posts.location',
            'posts.tagged_pets',
            'posts.metadata',
            'posts.is_pinned',
            'posts.likes_count',
            'posts.comments_count',
            'posts.reactions_count',
            'posts.shares_count',
            'posts.published_at',
            'posts.created_at',
            'posts.updated_at',
            'posts.deleted_at',
        ])->where(function (Builder $mediaQuery): void {
            $mediaQuery
                ->whereHas('postMedia')
                ->orWhereHas('media');
        });
    }

    public function scopeByTag(Builder $query, string $slug): Builder
    {
        $normalizedSlug = Str::of($slug)->trim()->lower()->toString();

        if ($normalizedSlug === '') {
            return $query->whereKey(-1);
        }

        return $query->select([
            'posts.id',
            'posts.user_id',
            'posts.group_id',
            'posts.pet_id',
            'posts.body',
            'posts.body_html',
            'posts.type',
            'posts.visibility',
            'posts.status',
            'posts.location',
            'posts.tagged_pets',
            'posts.metadata',
            'posts.is_pinned',
            'posts.likes_count',
            'posts.comments_count',
            'posts.reactions_count',
            'posts.shares_count',
            'posts.published_at',
            'posts.created_at',
            'posts.updated_at',
            'posts.deleted_at',
        ])->whereHas('hashtags', fn (Builder $hashtagQuery): Builder => $hashtagQuery->where('hashtags.slug', $normalizedSlug));
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
            ->orderByDesc('likes_count')
            ->orderByDesc('comments_count')
            ->orderByDesc('created_at');
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

    public function scopeExploreSearch(Builder $query, string $term): void
    {
        $clean = Str::limit(trim($term), 100, '');

        $query->where(function (Builder $searchQuery) use ($clean): void {
            $searchQuery
                ->where('body', 'like', "%{$clean}%")
                ->orWhere('location', 'like', "%{$clean}%")
                ->orWhereHas('hashtags', fn (Builder $hashtagQuery) => $hashtagQuery->where('name', 'like', '%'.strtolower($clean).'%'))
                ->orWhereHas('user', function (Builder $userQuery) use ($clean): void {
                    $userQuery
                        ->where('name', 'like', "%{$clean}%")
                        ->orWhere('username', 'like', "%{$clean}%");
                });
        });
    }

    public static function paginateExploreResults(
        ?User $viewer,
        string $type = 'all',
        string $search = '',
        int $perPage = 15
    ): LengthAwarePaginator {
        $viewerId = (int) ($viewer?->getKey() ?? 0);

        return self::query()
            ->with([
                'user',
                'hashtags',
                'pet' => fn (Builder $petQuery): Builder => $petQuery->visibleTo($viewer),
            ])
            ->withListEngagement($viewerId)
            ->published()
            ->explorable($viewer)
            ->when($type === 'photos', fn (Builder $query) => $query->byType(self::TYPE_PHOTO))
            ->when($type === 'videos', fn (Builder $query) => $query->byType(self::TYPE_VIDEO))
            ->when($search !== '', fn (Builder $query) => $query->exploreSearch($search))
            ->when(
                $type === 'trending',
                fn (Builder $query) => $query
                    ->orderByDesc('likes_count')
                    ->orderByDesc('comments_count')
                    ->latest('posts.created_at'),
                fn (Builder $query) => $query->latest('posts.created_at')
            )
            ->paginate($perPage)
            ->withQueryString();
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
        $viewerId = (int) ($viewer?->getKey() ?? 0);

        return self::query()
            ->searchResultColumns()
            ->withListEngagement($viewerId)
            ->published()
            ->visibleTo($viewer)
            ->with([
                'pet' => fn (Builder $petQuery): Builder => $petQuery->visibleTo($viewer),
            ])
            ->when($term !== '', fn (Builder $query) => $query->search($term))
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
        $viewerId = (int) ($viewer?->getKey() ?? 0);

        return self::query()
            ->profileTimelineColumns()
            ->forProfile($profileOwner)
            ->with([
                'user',
                'hashtags',
                'pet' => fn (Builder $petQuery): Builder => $petQuery->visibleTo($viewer),
            ])
            ->withListEngagement($viewerId)
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
            ->with([
                'user',
                'hashtags',
                'pet' => fn (Builder $petQuery): Builder => $petQuery->visibleTo($profileOwner),
            ])
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

    public function scopeInGroupFeed(Builder $query, Group $group): Builder
    {
        return $query->where(function (Builder $groupQuery) use ($group): void {
            $groupQuery
                ->where('posts.group_id', $group->getKey())
                ->orWhereHas('sharedGroups', fn (Builder $sharedQuery) => $sharedQuery->where('groups.id', $group->getKey()));
        });
    }

    public static function paginateGroupFeed(Group $group, int $perPage = 15, string $cursorName = 'posts_cursor'): CursorPaginator
    {
        $viewerId = (int) (auth()->id() ?? 0);
        $viewer = auth()->user();

        return self::query()
            ->inGroupFeed($group)
            ->withFeedRelations($viewer)
            ->withFeedLikeExistsForViewer($viewerId)
            ->latest('posts.created_at')
            ->cursorPaginate($perPage, ['posts.*'], $cursorName)
            ->withQueryString();
    }

    public static function paginateEmpty(int $perPage = 15, string $cursorName = 'posts_cursor'): CursorPaginator
    {
        return self::query()
            ->whereKey(-1)
            ->cursorPaginate($perPage, ['posts.*'], $cursorName)
            ->withQueryString();
    }

    public static function paginateMainFeedResults(User $viewer, ?string $type = null, int $perPage = 15): CursorPaginator
    {
        $hasFollowing = $viewer->relationLoaded('acceptedFollowing')
            ? $viewer->acceptedFollowing->isNotEmpty()
            : $viewer->acceptedFollowing()->exists();

        $viewerId = (int) $viewer->getKey();

        return self::query()
            ->when(
                $hasFollowing,
                fn (Builder $query) => $query->forFeed($viewerId),
                fn (Builder $query) => $query->visibleTo($viewer)
            )
            ->withFeedRelations($viewer)
            ->withFeedLikeExistsForViewer($viewerId)
            ->when($type !== null, fn (Builder $query) => $query->byType($type))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  list<int|string>  $postIds
     * @return Collection<int, Reaction>
     */
    public static function reactionMapForViewer(User $viewer, array $postIds): Collection
    {
        if ($postIds === []) {
            return collect();
        }

        return $viewer->reactions()
            ->whereIn('reactable_id', $postIds)
            ->where('reactable_type', self::class)
            ->get()
            ->keyBy('reactable_id');
    }

    /**
     * @param  list<int|string>  $postIds
     * @return Collection<int|string, int|string>
     */
    public static function savedMapForViewer(User $viewer, array $postIds): Collection
    {
        if ($postIds === []) {
            return collect();
        }

        return $viewer->savedPosts()
            ->whereIn('posts.id', $postIds)
            ->pluck('posts.id')
            ->flip();
    }

    public function scopeForFeed(Builder $query, int $userId): Builder
    {
        $query->select(['posts.*']);

        $followedUserIdsQuery = Follow::query()
            ->select('follows.following_id')
            ->where('follows.follower_id', $userId)
            ->where('follows.status', 'accepted');

        return $query
            ->published()
            ->where(function (Builder $feedQuery) use ($userId, $followedUserIdsQuery): void {
                $feedQuery
                    ->where('posts.user_id', $userId)
                    ->orWhere(function (Builder $followingQuery) use ($followedUserIdsQuery): void {
                        $followingQuery
                            ->whereIn('posts.user_id', $followedUserIdsQuery)
                            ->whereIn('posts.visibility', [self::VISIBILITY_PUBLIC, self::VISIBILITY_FOLLOWERS]);
                    })
                    ->orWhere(function (Builder $followedPetsQuery) use ($userId): void {
                        $followedPetsQuery
                            ->where('posts.user_id', '!=', $userId)
                            ->whereIn('posts.visibility', [self::VISIBILITY_PUBLIC, self::VISIBILITY_FOLLOWERS])
                            ->whereHas('pet.followers', fn (Builder $followersQuery): Builder => $followersQuery->where('users.id', $userId));
                    });
            })
            ->whereHas('author', function (Builder $authorQuery): void {
                $authorQuery->where('is_banned', false);
            })
            ->whereNull('posts.group_id')
            ->whereNotIn('posts.user_id', Block::query()->select('blocks.blocked_id')->where('blocks.blocker_id', $userId))
            ->whereNotIn('posts.user_id', Block::query()->select('blocks.blocker_id')->where('blocks.blocked_id', $userId));
    }

    public function scopeWithFeedRelations(Builder $query, ?User $viewer = null): Builder
    {
        return $query
            ->with([
                'user',
                'author',
                'pet' => fn (Builder $petQuery): Builder => $petQuery
                    ->visibleTo($viewer)
                    ->with('media'),
                'media',
                'tags',
            ])
            ->withListEngagement($viewer?->getKey());
    }

    public function scopeWithListEngagement(Builder $query, ?int $viewerId = null): Builder
    {
        $query->withCount([
            'likes',
            'comments',
        ]);

        $viewerId = (int) ($viewerId ?? 0);

        if ($viewerId <= 0) {
            return $query;
        }

        return $query->withExists([
            'likes' => fn (Builder $likeQuery): Builder => $likeQuery
                ->where('likes.user_id', $viewerId),
            'likes as liked_by_viewer' => fn (Builder $likeQuery): Builder => $likeQuery
                ->where('likes.user_id', $viewerId),
        ]);
    }

    public function scopeWithFeedLikeExistsForViewer(Builder $query, ?int $viewerId): Builder
    {
        $viewerId = (int) ($viewerId ?? 0);

        return $query->withExists([
            'likes' => fn (Builder $likeQuery): Builder => $likeQuery
                ->where('likes.user_id', $viewerId),
            'likes as liked_by_viewer' => fn (Builder $likeQuery) => $likeQuery
                ->where('likes.user_id', $viewerId),
        ]);
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
