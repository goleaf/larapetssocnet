<?php

namespace App\Models\Content;

use App\Enums\PostStatus;
use App\Models\Groups\Group;
use App\Models\Identity\User;
use App\Models\Moderation\Report;
use App\Models\Pets\Pet;
use App\Models\Social\Block;
use App\Models\Social\Follow;
use App\Services\ProfilePostOrderingService;
use App\Services\VisibilityService;
use App\Support\Hashtags\HashtagNormalizer;
use App\Traits\HasCounterCache;
use Carbon\CarbonImmutable;
use Database\Factories\PostFactory;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
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

#[UseFactory(PostFactory::class)]
#[Appends([
    'current_user_reaction',
])]
#[Fillable([
    'user_id',
    'group_id',
    'pet_id',
    'body',
    'body_html',
    'metadata',
    'type',
    'status',
    'published_at',
    'visibility',
    'location',
    'tagged_pets',
    'is_pinned',
    'pinned_at',
    'edited_at',
    'likes_count',
    'comments_count',
    'reactions_count',
    'shares_count',
    'save_count',
])]
class Post extends Model implements HasMedia
{
    use HasCounterCache, HasFactory, InteractsWithMedia, SoftDeletes;

    public const TYPE_TEXT = 'text';

    public const TYPE_PHOTO = 'photo';

    public const TYPE_VIDEO = 'video';

    public const VISIBILITY_PUBLIC = 'public';

    public const VISIBILITY_FOLLOWERS = 'followers';

    public const VISIBILITY_FRIENDS = 'friends';

    public const VISIBILITY_PRIVATE = 'private';

    /**
     * @return list<string>
     */
    public static function visibilityValues(): array
    {
        return [
            self::VISIBILITY_PUBLIC,
            self::VISIBILITY_FOLLOWERS,
            self::VISIBILITY_FRIENDS,
            self::VISIBILITY_PRIVATE,
        ];
    }

    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
            'tagged_pets' => 'array',
            'group_id' => 'integer',
            'status' => PostStatus::class,
            'published_at' => 'datetime',
            'pinned_at' => 'datetime',
            'edited_at' => 'datetime',
            'metadata' => 'array',
            'likes_count' => 'integer',
            'comments_count' => 'integer',
            'reactions_count' => 'integer',
            'shares_count' => 'integer',
            'save_count' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

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

    public function belongsToArchivedGroup(): bool
    {
        if (! $this->group_id) {
            return false;
        }

        $this->loadMissing('group:id,status,archived_at');

        $group = $this->group;

        return $group instanceof Group && $group->isArchived();
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

    public function shares(): MorphMany
    {
        return $this->morphMany(Share::class, 'shareable');
    }

    public function reports(): MorphMany
    {
        return $this->morphMany(Report::class, 'reportable');
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
        if ($viewer instanceof User && $viewer->isUnavailableForProfile()) {
            $query->whereKey(-1);

            return;
        }

        if ($viewer?->hasAnyRole(['admin', 'moderator'])) {
            $query->whereHas('author', function (Builder $authorQuery): void {
                User::applyAvailableForProfiles($authorQuery);
            });

            $this->applyPetVisibilityScope($query, $viewer);

            return;
        }

        if (! $viewer instanceof User) {
            $query->where('visibility', self::VISIBILITY_PUBLIC)
                ->where('status', PostStatus::Published->value)
                ->where(function (Builder $publishedQuery): void {
                    $publishedQuery
                        ->whereNull('published_at')
                        ->orWhere('published_at', '<=', now());
                })
                ->whereHas('author', function (Builder $authorQuery): void {
                    User::applyAvailableForProfiles($authorQuery->where('is_private', false));
                });

            $this->applyPetVisibilityScope($query, $viewer);

            return;
        }

        $blockedIds = $viewer->blocking()
            ->pluck('blocked_id')
            ->merge($viewer->blockedBy()->pluck('blocker_id'))
            ->unique()
            ->values();

        $followingIdsQuery = static fn () => Follow::query()
            ->select('follows.following_id')
            ->where('follows.follower_id', $viewer->getKey())
            ->where('follows.status', 'accepted');

        $mutualFollowingIdsQuery = static fn () => Follow::query()
            ->from('follows as viewer_follows')
            ->select('viewer_follows.following_id')
            ->where('viewer_follows.follower_id', $viewer->getKey())
            ->where('viewer_follows.status', 'accepted')
            ->whereIn('viewer_follows.following_id', Follow::query()
                ->from('follows as author_follows')
                ->select('author_follows.follower_id')
                ->where('author_follows.following_id', $viewer->getKey())
                ->where('author_follows.status', 'accepted'));

        $query->where(function (Builder $visibilityQuery) use ($viewer, $blockedIds, $followingIdsQuery, $mutualFollowingIdsQuery): void {
            $visibilityQuery
                ->where('user_id', $viewer->getKey())
                ->orWhere(function (Builder $otherPostsQuery) use ($viewer, $blockedIds, $followingIdsQuery, $mutualFollowingIdsQuery): void {
                    $otherPostsQuery
                        ->where('user_id', '!=', $viewer->getKey())
                        ->when($blockedIds->isNotEmpty(), function (Builder $blockedQuery) use ($blockedIds): void {
                            $blockedQuery->whereNotIn('user_id', $blockedIds);
                        })
                        ->whereHas('author', function (Builder $authorQuery): void {
                            User::applyAvailableForProfiles($authorQuery);
                        })
                        ->where('status', PostStatus::Published->value)
                        ->where(function (Builder $publishedQuery): void {
                            $publishedQuery
                                ->whereNull('published_at')
                                ->orWhere('published_at', '<=', now());
                        })
                        ->where(function (Builder $rulesQuery) use ($followingIdsQuery, $mutualFollowingIdsQuery): void {
                            $rulesQuery
                                ->where(function (Builder $publicFromPublicAccounts): void {
                                    $publicFromPublicAccounts
                                        ->where('visibility', self::VISIBILITY_PUBLIC)
                                        ->whereHas('author', function (Builder $authorQuery): void {
                                            $authorQuery->where('is_private', false);
                                        });
                                })
                                ->orWhere(function (Builder $followersOnlyQuery) use ($followingIdsQuery): void {
                                    $followersOnlyQuery
                                        ->where('visibility', self::VISIBILITY_FOLLOWERS)
                                        ->whereIn('user_id', $followingIdsQuery());
                                })
                                ->orWhere(function (Builder $friendsOnlyQuery) use ($mutualFollowingIdsQuery): void {
                                    $friendsOnlyQuery
                                        ->where('visibility', self::VISIBILITY_FRIENDS)
                                        ->whereIn('user_id', $mutualFollowingIdsQuery());
                                })
                                ->orWhere(function (Builder $publicFromPrivateAccounts) use ($followingIdsQuery): void {
                                    $publicFromPrivateAccounts
                                        ->where('visibility', self::VISIBILITY_PUBLIC)
                                        ->whereHas('author', function (Builder $authorQuery): void {
                                            $authorQuery->where('is_private', true);
                                        })
                                        ->whereIn('user_id', $followingIdsQuery());
                                });
                        });
                });
        });

        $this->applyPetVisibilityScope($query, $viewer);
    }

    protected function applyPetVisibilityScope(Builder $query, ?User $viewer): void
    {
        $query->where(function (Builder $petQuery) use ($viewer): void {
            $petQuery
                ->whereNull('posts.pet_id')
                ->orWhereHas('pet', fn (Builder $petQuery): Builder => $petQuery->visibleTo($viewer));
        });
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query->where('visibility', 'public');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->select(['posts.*'])
            ->where('posts.status', PostStatus::Published->value)
            ->where(function (Builder $publishedQuery): void {
                $publishedQuery
                    ->whereNull('posts.published_at')
                    ->orWhere('posts.published_at', '<=', now());
            });
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->select(['posts.*'])->where('posts.status', PostStatus::Draft->value);
    }

    public function scopeScheduled(Builder $query): Builder
    {
        return $query->select(['posts.*'])->where('posts.status', PostStatus::Scheduled->value);
    }

    public function scopeArchived(Builder $query): Builder
    {
        return $query->select(['posts.*'])->where('posts.status', PostStatus::Archived->value);
    }

    public function scopePublicFeedEligible(Builder $query): Builder
    {
        return $query
            ->published()
            ->where('posts.visibility', self::VISIBILITY_PUBLIC);
    }

    public function scopeNotBlockedFor(Builder $query, ?User $viewer): void
    {
        if (! $viewer instanceof User) {
            return;
        }

        $query
            ->whereNotIn('posts.user_id', $viewer->blocking()->select('users.id'))
            ->whereNotIn('posts.user_id', $viewer->blockedBy()->select('users.id'));
    }

    public function scopeByType(Builder $query, string $type): void
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
            'posts.published_at',
            'posts.location',
            'posts.tagged_pets',
            'posts.metadata',
            'posts.is_pinned',
            'posts.pinned_at',
            'posts.edited_at',
            'posts.likes_count',
            'posts.comments_count',
            'posts.reactions_count',
            'posts.shares_count',
            'posts.save_count',
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
            'posts.published_at',
            'posts.location',
            'posts.tagged_pets',
            'posts.metadata',
            'posts.is_pinned',
            'posts.pinned_at',
            'posts.edited_at',
            'posts.likes_count',
            'posts.comments_count',
            'posts.reactions_count',
            'posts.shares_count',
            'posts.save_count',
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
        $normalizer = new HashtagNormalizer;
        $normalizedSlug = $normalizer->normalizeFromSlug($slug);

        if (! $normalizedSlug) {
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
            'posts.published_at',
            'posts.location',
            'posts.tagged_pets',
            'posts.metadata',
            'posts.is_pinned',
            'posts.pinned_at',
            'posts.edited_at',
            'posts.likes_count',
            'posts.comments_count',
            'posts.reactions_count',
            'posts.shares_count',
            'posts.save_count',
            'posts.created_at',
            'posts.updated_at',
            'posts.deleted_at',
        ])->whereHas('hashtags', fn (Builder $hashtagQuery): Builder => $hashtagQuery->where('hashtags.normalized_name', $normalizedSlug));
    }

    public function scopeExplorable(Builder $query, ?User $viewer): void
    {
        if ($viewer instanceof User && $viewer->isUnavailableForProfile()) {
            $query->whereKey(-1);

            return;
        }

        $query
            ->where('visibility', self::VISIBILITY_PUBLIC)
            ->where('status', PostStatus::Published->value)
            ->where(function (Builder $publishedQuery): void {
                $publishedQuery
                    ->whereNull('posts.published_at')
                    ->orWhere('posts.published_at', '<=', now());
            })
            ->whereHas('author', function (Builder $authorQuery): void {
                User::applyAvailableForProfiles($authorQuery->where('is_private', false));
            });

        if ($viewer instanceof User) {
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

    public function scopeTrending(Builder $query): void
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

    public function scopeTopRated(Builder $query): void
    {
        $query
            ->orderByDesc('likes_count')
            ->orderByDesc('created_at');
    }

    public function scopeSearch(Builder $query, string $term): void
    {
        $clean = Str::limit(trim($term), 100, '');
        $normalizer = new HashtagNormalizer;
        $normalizedTag = $normalizer->normalizeFromInput($clean);

        $query->where(function (Builder $searchQuery) use ($clean, $normalizedTag): void {
            $searchQuery
                ->where('body', 'like', "%{$clean}%")
                ->orWhereHas('hashtags', function (Builder $hashtagQuery) use ($clean, $normalizedTag): void {
                    $hashtagQuery->where('name', 'like', "%{$clean}%")
                        ->orWhere('normalized_name', 'like', "%{$clean}%");

                    if ($normalizedTag) {
                        $hashtagQuery->orWhere('normalized_name', $normalizedTag);
                    }
                })
                ->orWhere('location', 'like', "%{$clean}%");
        });
    }

    public function scopeExploreSearch(Builder $query, string $term): void
    {
        $clean = Str::limit(trim($term), 100, '');
        $normalizer = new HashtagNormalizer;
        $normalizedTag = $normalizer->normalizeFromInput($clean);

        $query->where(function (Builder $searchQuery) use ($clean, $normalizedTag): void {
            $searchQuery
                ->where('body', 'like', "%{$clean}%")
                ->orWhere('location', 'like', "%{$clean}%")
                ->orWhereHas('hashtags', function (Builder $hashtagQuery) use ($clean, $normalizedTag): void {
                    $hashtagQuery->where('name', 'like', '%'.strtolower($clean).'%')
                        ->orWhere('normalized_name', 'like', '%'.strtolower($clean).'%');

                    if ($normalizedTag) {
                        $hashtagQuery->orWhere('normalized_name', $normalizedTag);
                    }
                })
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
                'user.media',
                'author.media',
                'hashtags',
                'pet' => fn (BelongsTo $petQuery): BelongsTo => $petQuery->visibleTo($viewer),
            ])
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
            ->withListEngagement($viewerId)
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
            'posts.published_at',
            'posts.created_at',
            'posts.is_pinned',
        ]);
    }

    public static function paginateSearchResults(?User $viewer, string $term, int $perPage = 15): LengthAwarePaginator
    {
        $viewerId = (int) ($viewer?->getKey() ?? 0);

        return self::query()
            ->searchResultColumns()
            ->published()
            ->visibleTo($viewer)
            ->with([
                'pet' => fn (BelongsTo $petQuery): BelongsTo => $petQuery->visibleTo($viewer),
            ])
            ->when($term !== '', fn (Builder $query) => $query->search($term))
            ->latest('posts.created_at')
            ->withListEngagement($viewerId)
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
            'posts.pinned_at',
            'posts.edited_at',
            'posts.published_at',
            'posts.likes_count',
            'posts.comments_count',
            'posts.shares_count',
            'posts.save_count',
            'posts.created_at',
        ]);
    }

    public function scopeForProfile(Builder $query, User $user): Builder
    {
        return $query->where('posts.user_id', $user->getKey());
    }

    public static function pinnedProfileTimelinePost(User $profileOwner, ?User $viewer): ?self
    {
        $viewerId = (int) ($viewer?->getKey() ?? 0);
        $isOwner = $viewer instanceof User && $viewer->is($profileOwner);

        return self::query()
            ->profileTimelineColumns()
            ->forProfile($profileOwner)
            ->where('posts.is_pinned', true)
            ->with([
                'user',
                'author.media',
                'hashtags',
                'pet' => fn (BelongsTo $petQuery): BelongsTo => $petQuery->visibleTo($viewer),
            ])
            ->when(
                $isOwner,
                fn (Builder $query): Builder => $query->where('posts.status', '!=', PostStatus::Archived->value),
                fn (Builder $query): Builder => $query->published(),
            )
            ->visibleTo($viewer)
            ->orderByDesc('posts.pinned_at')
            ->withListEngagement($viewerId)
            ->first();
    }

    public static function paginateProfileTimeline(User $profileOwner, ?User $viewer, int $perPage = 10): LengthAwarePaginator
    {
        $viewerId = (int) ($viewer?->getKey() ?? 0);
        $isOwner = $viewer instanceof User && $viewer->is($profileOwner);

        return self::query()
            ->profileTimelineColumns()
            ->forProfile($profileOwner)
            ->with([
                'user',
                'author.media',
                'hashtags',
                'pet' => fn (BelongsTo $petQuery): BelongsTo => $petQuery->visibleTo($viewer),
            ])
            ->when(
                $isOwner,
                fn (Builder $query): Builder => $query->where('posts.status', '!=', PostStatus::Archived->value),
                fn (Builder $query): Builder => $query->published(),
            )
            ->visibleTo($viewer)
            ->when(true, fn (Builder $query) => app(ProfilePostOrderingService::class)->apply($query))
            ->withListEngagement($viewerId)
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
            ->published()
            ->with([
                'user',
                'author.media',
                'hashtags',
                'pet' => fn (BelongsTo $petQuery): BelongsTo => $petQuery->visibleTo($profileOwner),
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
            ->published()
            ->count();
    }

    /**
     * @return Collection<int, self>
     */
    public static function recentDraftsForProfileOwner(User $profileOwner, int $limit = 10): Collection
    {
        return self::query()
            ->profileTimelineColumns()
            ->forProfile($profileOwner)
            ->draft()
            ->with([
                'user',
                'author.media',
                'hashtags',
                'pet' => fn (BelongsTo $petQuery): BelongsTo => $petQuery->visibleTo($profileOwner),
            ])
            ->latest('posts.created_at')
            ->limit($limit)
            ->get();
    }

    public static function draftCountForProfile(User $profileOwner): int
    {
        return (int) self::query()
            ->forProfile($profileOwner)
            ->draft()
            ->count();
    }

    /**
     * @return Collection<int, self>
     */
    public static function recentScheduledForProfileOwner(User $profileOwner, int $limit = 10): Collection
    {
        return self::query()
            ->profileTimelineColumns()
            ->forProfile($profileOwner)
            ->scheduled()
            ->with([
                'user',
                'author.media',
                'hashtags',
                'pet' => fn (BelongsTo $petQuery): BelongsTo => $petQuery->visibleTo($profileOwner),
            ])
            ->latest('posts.published_at')
            ->limit($limit)
            ->get();
    }

    public static function scheduledCountForProfile(User $profileOwner): int
    {
        return (int) ($profileOwner->scheduled_posts_count ?? 0);
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
                'count' => $countsByMonth[$monthKey] ?? 0,
            ];
        }

        return $summary;
    }

    public function scopePinned(Builder $query): void
    {
        $query->where('is_pinned', true);
    }

    public function scopePinnedFirst(Builder $query): Builder
    {
        return $query
            ->orderByDesc('posts.is_pinned')
            ->orderByDesc('posts.pinned_at')
            ->orderByDesc('posts.created_at')
            ->orderByDesc('posts.id');
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

    public static function paginateGroupFeed(Group $group, ?User $viewer = null, int $perPage = 15, string $cursorName = 'posts_cursor'): CursorPaginator
    {
        $viewerId = (int) ($viewer?->getKey() ?? 0);

        return self::query()
            ->inGroupFeed($group)
            ->visibleTo($viewer)
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

    public static function paginateMainFeedResults(User $viewer, ?string $type = null, int $perPage = 15, ?string $source = null): CursorPaginator
    {
        $viewerId = (int) $viewer->getKey();

        return self::query()
            ->forFeed($viewerId)
            ->forFeedSource($viewerId, $source)
            ->withFeedRelations($viewer)
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
            ->where('reactable_type', (new self)->getMorphClass())
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

        $mutualUserIdsQuery = Follow::query()
            ->select('follows.following_id')
            ->where('follows.follower_id', $userId)
            ->where('follows.status', 'accepted')
            ->whereIn('follows.following_id', Follow::query()
                ->select('follows.follower_id')
                ->where('follows.following_id', $userId)
                ->where('follows.status', 'accepted'));

        return $query
            ->published()
            ->where(function (Builder $feedQuery) use ($userId, $followedUserIdsQuery, $mutualUserIdsQuery): void {
                $feedQuery
                    ->where('posts.user_id', $userId)
                    ->orWhere(function (Builder $followingQuery) use ($followedUserIdsQuery, $mutualUserIdsQuery): void {
                        $followingQuery
                            ->whereIn('posts.user_id', $followedUserIdsQuery)
                            ->where(function (Builder $visibilityQuery) use ($mutualUserIdsQuery): void {
                                $visibilityQuery
                                    ->whereIn('posts.visibility', [self::VISIBILITY_PUBLIC, self::VISIBILITY_FOLLOWERS])
                                    ->orWhere(function (Builder $friendsQuery) use ($mutualUserIdsQuery): void {
                                        $friendsQuery
                                            ->where('posts.visibility', self::VISIBILITY_FRIENDS)
                                            ->whereIn('posts.user_id', $mutualUserIdsQuery);
                                    });
                            });
                    })
                    ->orWhere(function (Builder $followedPetsQuery) use ($userId, $mutualUserIdsQuery): void {
                        $followedPetsQuery
                            ->where('posts.user_id', '!=', $userId)
                            ->where(function (Builder $visibilityQuery) use ($mutualUserIdsQuery): void {
                                $visibilityQuery
                                    ->whereIn('posts.visibility', [self::VISIBILITY_PUBLIC, self::VISIBILITY_FOLLOWERS])
                                    ->orWhere(function (Builder $friendsQuery) use ($mutualUserIdsQuery): void {
                                        $friendsQuery
                                            ->where('posts.visibility', self::VISIBILITY_FRIENDS)
                                            ->whereIn('posts.user_id', $mutualUserIdsQuery);
                                    });
                            })
                            ->whereHas('pet.followers', fn (Builder $followersQuery): Builder => $followersQuery->where('users.id', $userId));
                    });
            })
            ->whereHas('author', function (Builder $authorQuery): void {
                User::applyAvailableForProfiles($authorQuery);
            })
            ->whereNull('posts.group_id')
            ->whereNotIn('posts.user_id', Block::query()->select('blocks.blocked_id')->where('blocks.blocker_id', $userId))
            ->whereNotIn('posts.user_id', Block::query()->select('blocks.blocker_id')->where('blocks.blocked_id', $userId));
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForFeedSource(Builder $query, int $userId, ?string $source): Builder
    {
        return match ($source) {
            'people' => $query->where(function (Builder $peopleQuery) use ($userId): void {
                $peopleQuery
                    ->where('posts.user_id', $userId)
                    ->orWhereIn('posts.user_id', Follow::query()
                        ->select('follows.following_id')
                        ->where('follows.follower_id', $userId)
                        ->where('follows.status', 'accepted'));
            }),
            'pets' => $query->where(function (Builder $petsQuery) use ($userId): void {
                $petsQuery
                    ->where('posts.user_id', '!=', $userId)
                    ->whereHas('pet.followers', fn (Builder $followersQuery): Builder => $followersQuery->where('users.id', $userId));
            }),
            default => $query,
        };
    }

    public function scopeWithFeedRelations(Builder $query, ?User $viewer = null): Builder
    {
        return $query
            ->with([
                'user',
                'user.media',
                'pet.media',
                'media',
                'tags',
            ])
            ->withListEngagement($viewer?->getKey());
    }

    public function scopeWithListEngagement(Builder $query, ?int $viewerId = null): Builder
    {
        $query->withCount([
            'reactions as likes_count',
            'comments as comments_count',
        ]);

        $viewerId = $viewerId ?? 0;

        if ($viewerId <= 0) {
            return $query;
        }

        return $query
            ->withExists([
                'reactions as liked_by_viewer' => fn (Builder $reactionQuery): Builder => $reactionQuery
                    ->where('reactions.user_id', $viewerId),
                'savedBy as saved_by_viewer' => fn (Builder $saveQuery): Builder => $saveQuery
                    ->where('saved_posts.user_id', $viewerId),
            ]);
    }

    public function scopeWithFeedLikeExistsForViewer(Builder $query, ?int $viewerId): Builder
    {
        $viewerId = $viewerId ?? 0;

        return $query->withExists([
            'reactions as liked_by_viewer' => fn (Builder $reactionQuery) => $reactionQuery
                ->where('reactions.user_id', $viewerId),
            'savedBy as saved_by_viewer' => fn (Builder $saveQuery): Builder => $saveQuery
                ->where('saved_posts.user_id', $viewerId),
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
            ->map(fn ($m): array => [
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
        if ($this->relationLoaded('reactions')) {
            return $this->reactions->contains(function (Reaction $reaction) use ($user): bool {
                return $reaction->user_id === $user->getKey();
            });
        }

        return $this->reactions()->where('user_id', $user->getKey())->exists();
    }

    public function refreshLikesCount(): void
    {
        $count = (int) $this->reactions()->count();

        $this->update([
            'likes_count' => $count,
            'reactions_count' => $count,
        ]);
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
        if ($this->relationLoaded('reactions')) {
            $type = $this->reactions->firstWhere('user_id', auth()->id())?->type;

            return $type ? Reaction::normalizeType((string) $type) : null;
        }

        $type = $this->reactions()->where('user_id', auth()->id())->value('type');

        return $type ? Reaction::normalizeType((string) $type) : null;
    }

    public function displayAuthor(): ?User
    {
        return $this->user;
    }

    public function displayPhotos(): Collection
    {
        return collect($this->getMedia('photos'))->merge($this->getMedia('images'));
    }

    public function displayVideo(): ?Media
    {
        return $this->getFirstMedia('video');
    }

    public function visibilityLabel(): string
    {
        return ucfirst((string) ($this->visibility ?: self::VISIBILITY_PUBLIC));
    }

    /**
     * @return array<string, string>
     */
    public static function reactionEmojiMap(): array
    {
        return Reaction::emojiMap();
    }

    public function canBeViewedBy(?User $viewer): bool
    {
        return app(VisibilityService::class)->canView($viewer, $this);
    }
}
