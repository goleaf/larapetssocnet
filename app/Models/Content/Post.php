<?php

namespace App\Models\Content;

use App\Enums\PostStatus;
use App\Models\Groups\Group;
use App\Models\Identity\User;
use App\Models\Moderation\Report;
use App\Models\Pets\Pet;
use App\Models\Social\Block;
use App\Models\Social\FeedItem;
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
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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
    'uuid',
    'author_type',
    'author_id',
    'group_id',
    'pet_id',
    'body',
    'content_hash',
    'body_html',
    'metadata',
    'link_preview',
    'type',
    'status',
    'published_at',
    'scheduled_publish_at',
    'visibility',
    'mood',
    'location',
    'location_display_text',
    'location_lat',
    'location_lng',
    'tagged_pets',
    'is_pinned',
    'is_system_generated',
    'is_fanned_out',
    'system_source',
    'original_post_id',
    'quote_post_id',
    'pinned_at',
    'edited_at',
    'edit_count',
    'likes_count',
    'comments_count',
    'reactions_count',
    'love_count',
    'cute_count',
    'funny_count',
    'wow_count',
    'sad_count',
    'support_count',
    'shares_count',
    'view_count',
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

    public const FEED_RANKING_LATEST = User::FEED_RANKING_LATEST;

    public const FEED_RANKING_BEST = User::FEED_RANKING_BEST;

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
            'is_system_generated' => 'boolean',
            'is_fanned_out' => 'boolean',
            'uuid' => 'string',
            'content_hash' => 'string',
            'tagged_pets' => 'array',
            'group_id' => 'integer',
            'status' => PostStatus::class,
            'published_at' => 'datetime',
            'scheduled_publish_at' => 'datetime',
            'pinned_at' => 'datetime',
            'edited_at' => 'datetime',
            'edit_count' => 'integer',
            'metadata' => 'array',
            'link_preview' => 'array',
            'likes_count' => 'integer',
            'comments_count' => 'integer',
            'reactions_count' => 'integer',
            'love_count' => 'integer',
            'cute_count' => 'integer',
            'funny_count' => 'integer',
            'wow_count' => 'integer',
            'sad_count' => 'integer',
            'support_count' => 'integer',
            'shares_count' => 'integer',
            'view_count' => 'integer',
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

    protected static function booted(): void
    {
        static::creating(function (self $post): void {
            if (! filled($post->uuid)) {
                $post->uuid = (string) Str::uuid();
            }
        });
    }

    public function resolveRouteBinding($value, $field = null)
    {
        if ($field !== null) {
            return parent::resolveRouteBinding($value, $field);
        }

        $query = $this->newQuery()->where('uuid', $value);

        if (ctype_digit((string) $value)) {
            $query->orWhere($this->getKeyName(), (int) $value);
        }

        return $query->first();
    }

    // Relationships

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function contentAuthor(): MorphTo
    {
        return $this->morphTo('author');
    }

    public function user(): BelongsTo
    {
        return $this->author();
    }

    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }

    /**
     * @return BelongsToMany<Pet, $this>
     */
    public function pets(): BelongsToMany
    {
        return $this->belongsToMany(Pet::class, 'pet_post')
            ->withPivot(['is_primary'])
            ->withTimestamps();
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

    public function postMentions(): HasMany
    {
        return $this->hasMany(PostMention::class);
    }

    public function feedItems(): HasMany
    {
        return $this->hasMany(FeedItem::class);
    }

    public function mentionedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'post_mentions', 'post_id', 'mentioned_user_id')
            ->withPivot(['mentioned_username'])
            ->withTimestamps();
    }

    public function originalPost(): BelongsTo
    {
        return $this->belongsTo(self::class, 'original_post_id');
    }

    public function quotePost(): BelongsTo
    {
        return $this->belongsTo(self::class, 'quote_post_id');
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
                ->orWhereHas('pet', fn (Builder $petQuery): Builder => $petQuery->visibleTo($viewer))
                ->orWhereHas('pets', fn (Builder $petTagQuery): Builder => $petTagQuery->visibleTo($viewer));
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

    public function scopeDueForPublication(Builder $query): Builder
    {
        return $query
            ->select(['posts.*'])
            ->where('posts.status', PostStatus::Scheduled->value)
            ->whereNotNull('posts.scheduled_publish_at')
            ->where('posts.scheduled_publish_at', '<=', now());
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
            'posts.content_hash',
            'posts.body_html',
            'posts.type',
            'posts.visibility',
            'posts.mood',
            'posts.status',
            'posts.published_at',
            'posts.scheduled_publish_at',
            'posts.location',
            'posts.location_display_text',
            'posts.tagged_pets',
            'posts.metadata',
            'posts.link_preview',
            'posts.is_pinned',
            'posts.is_system_generated',
            'posts.system_source',
            'posts.original_post_id',
            'posts.quote_post_id',
            'posts.pinned_at',
            'posts.edited_at',
            'posts.edit_count',
            'posts.likes_count',
            'posts.comments_count',
            'posts.reactions_count',
            'posts.shares_count',
            'posts.save_count',
            'posts.created_at',
            'posts.updated_at',
            'posts.deleted_at',
        ])->where(function (Builder $petQuery) use ($petId): void {
            $petQuery
                ->where('posts.pet_id', (int) $petId)
                ->orWhereHas('pets', fn (Builder $taggedPetQuery): Builder => $taggedPetQuery->whereKey((int) $petId));
        });
    }

    public function scopeWithMedia(Builder $query): Builder
    {
        $query->select([
            'posts.id',
            'posts.user_id',
            'posts.group_id',
            'posts.pet_id',
            'posts.body',
            'posts.body_html',
            'posts.type',
            'posts.visibility',
            'posts.mood',
            'posts.status',
            'posts.published_at',
            'posts.scheduled_publish_at',
            'posts.location',
            'posts.location_display_text',
            'posts.tagged_pets',
            'posts.metadata',
            'posts.link_preview',
            'posts.is_pinned',
            'posts.is_system_generated',
            'posts.system_source',
            'posts.original_post_id',
            'posts.quote_post_id',
            'posts.pinned_at',
            'posts.edited_at',
            'posts.edit_count',
            'posts.likes_count',
            'posts.comments_count',
            'posts.reactions_count',
            'posts.shares_count',
            'posts.save_count',
            'posts.created_at',
            'posts.updated_at',
            'posts.deleted_at',
        ]);

        return self::applyContainingMediaFilter($query);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeContainingMedia(Builder $query): Builder
    {
        return self::applyContainingMediaFilter($query);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeContainingPhotos(Builder $query): Builder
    {
        return $query->where(function (Builder $mediaQuery): void {
            $mediaQuery
                ->whereHas('postMedia', function (Builder $postMediaQuery): void {
                    $postMediaQuery->where('media_type', 'image');
                })
                ->orWhereHas('media', function (Builder $spatieMediaQuery): void {
                    $spatieMediaQuery->whereIn('collection_name', ['photos', 'images']);
                });
        });
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    private static function applyContainingMediaFilter(Builder $query): Builder
    {
        return $query->where(function (Builder $mediaQuery): void {
            $mediaQuery
                ->whereHas('postMedia')
                ->orWhereHas('media', function (Builder $spatieMediaQuery): void {
                    $spatieMediaQuery->whereIn('collection_name', ['photos', 'images', 'videos', 'video']);
                });
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
            'posts.mood',
            'posts.status',
            'posts.published_at',
            'posts.scheduled_publish_at',
            'posts.location',
            'posts.location_display_text',
            'posts.tagged_pets',
            'posts.metadata',
            'posts.link_preview',
            'posts.is_pinned',
            'posts.is_system_generated',
            'posts.system_source',
            'posts.original_post_id',
            'posts.quote_post_id',
            'posts.pinned_at',
            'posts.edited_at',
            'posts.edit_count',
            'posts.likes_count',
            'posts.comments_count',
            'posts.reactions_count',
            'posts.shares_count',
            'posts.save_count',
            'posts.created_at',
            'posts.updated_at',
            'posts.deleted_at',
        ])
            ->join('post_hashtag as hashtag_posts', 'hashtag_posts.post_id', '=', 'posts.id')
            ->join('hashtags as matched_hashtags', 'matched_hashtags.id', '=', 'hashtag_posts.hashtag_id')
            ->where('matched_hashtags.normalized_name', $normalizedSlug);
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
                'originalPost.author.media',
                'originalPost.postMedia',
                'quotePost.author.media',
                'quotePost.postMedia',
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
            'posts.pet_id',
            'posts.body',
            'posts.body_html',
            'posts.status',
            'posts.visibility',
            'posts.mood',
            'posts.location',
            'posts.location_display_text',
            'posts.tagged_pets',
            'posts.is_pinned',
            'posts.is_system_generated',
            'posts.system_source',
            'posts.original_post_id',
            'posts.quote_post_id',
            'posts.pinned_at',
            'posts.edited_at',
            'posts.edit_count',
            'posts.published_at',
            'posts.scheduled_publish_at',
            'posts.link_preview',
            'posts.likes_count',
            'posts.comments_count',
            'posts.reactions_count',
            'posts.shares_count',
            'posts.save_count',
            'posts.created_at',
        ]);
    }

    public function scopeForProfile(Builder $query, User $user): Builder
    {
        return $query->where('posts.user_id', $user->getKey());
    }

    public function scopeAuthoredBy(Builder $query, Model $author): Builder
    {
        return $query
            ->where('posts.author_type', $author::class)
            ->where('posts.author_id', $author->getKey());
    }

    /**
     * @return Builder<self>
     */
    private static function profileTimelineQuery(User $profileOwner, ?User $viewer, bool $mediaOnly = false): Builder
    {
        $viewerId = (int) ($viewer?->getKey() ?? 0);

        $query = self::query()
            ->profileTimelineColumns()
            ->with([
                'user',
                'author.media',
                'hashtags',
                'media',
                'postMedia',
                'originalPost.author.media',
                'originalPost.postMedia',
                'quotePost.author.media',
                'quotePost.postMedia',
            ])
            ->with([
                'pet' => fn ($petQuery) => $petQuery->visibleTo($viewer),
            ])
            ->when($mediaOnly, fn (Builder $query): Builder => self::applyContainingMediaFilter($query));

        return self::applyProfileTimelineVisibility($query, $profileOwner, $viewer)
            ->withListEngagement($viewerId);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    private static function applyProfileTimelineVisibility(Builder $query, User $profileOwner, ?User $viewer): Builder
    {
        $isOwner = $viewer instanceof User && $viewer->is($profileOwner);

        return $query
            ->forProfile($profileOwner)
            ->when(
                $isOwner,
                fn (Builder $query): Builder => $query->where('posts.status', '!=', PostStatus::Archived->value),
                fn (Builder $query): Builder => $query->published(),
            )
            ->visibleTo($viewer);
    }

    public static function pinnedProfileTimelinePost(User $profileOwner, ?User $viewer): ?self
    {
        return self::profileTimelineQuery($profileOwner, $viewer)
            ->where('posts.is_pinned', true)
            ->orderByDesc('posts.pinned_at')
            ->first();
    }

    /**
     * @return CursorPaginator<int, self>
     */
    public static function paginateProfileTimeline(User $profileOwner, ?User $viewer, int $perPage = 15, ?string $cursor = null, bool $mediaOnly = false): CursorPaginator
    {
        return self::profileTimelineQuery($profileOwner, $viewer, $mediaOnly)
            ->when(true, fn (Builder $query) => app(ProfilePostOrderingService::class)->apply($query))
            ->cursorPaginate($perPage, ['*'], 'profile_posts_cursor', $cursor)
            ->withQueryString();
    }

    /**
     * @param  list<int>  $postIds
     * @return Collection<int, self>
     */
    public static function profileTimelinePostsByIds(User $profileOwner, ?User $viewer, array $postIds, bool $mediaOnly = false): Collection
    {
        if ($postIds === []) {
            return collect();
        }

        $posts = self::profileTimelineQuery($profileOwner, $viewer, $mediaOnly)
            ->whereIn('posts.id', $postIds)
            ->get()
            ->keyBy(fn (self $post): int => (int) $post->getKey());

        return collect($postIds)
            ->map(fn (int $postId): ?self => $posts->get($postId))
            ->filter()
            ->values();
    }

    public static function profileTimelinePostForModal(User $profileOwner, ?User $viewer, int $postId, bool $mediaOnly = false): ?self
    {
        return self::profileTimelineQuery($profileOwner, $viewer, $mediaOnly)
            ->whereKey($postId)
            ->first();
    }

    /**
     * @return Collection<int, self>
     */
    public static function profilePhotoGridPosts(User $profileOwner, ?User $viewer): Collection
    {
        return self::profileTimelineQuery($profileOwner, $viewer)
            ->containingPhotos()
            ->when(true, fn (Builder $query) => app(ProfilePostOrderingService::class)->apply($query))
            ->get();
    }

    /**
     * @return Collection<int, PostMedia>
     */
    public static function profilePhotoMediaPage(User $profileOwner, ?User $viewer, int $perPage = 30, ?int $cursorId = null): Collection
    {
        return self::profilePhotoMediaQuery($profileOwner, $viewer)
            ->when($cursorId !== null, fn (Builder $query): Builder => $query->where('post_media.id', '<', $cursorId))
            ->orderByDesc('post_media.id')
            ->limit(max(1, $perPage) + 1)
            ->get();
    }

    /**
     * @param  list<int>  $mediaIds
     * @return Collection<int, PostMedia>
     */
    public static function profilePhotoMediaByIds(User $profileOwner, ?User $viewer, array $mediaIds): Collection
    {
        if ($mediaIds === []) {
            return collect();
        }

        $media = self::profilePhotoMediaQuery($profileOwner, $viewer)
            ->whereIn('post_media.id', $mediaIds)
            ->get()
            ->keyBy(fn (PostMedia $media): int => (int) $media->getKey());

        return collect($mediaIds)
            ->map(fn (int $mediaId): ?PostMedia => $media->get($mediaId))
            ->filter()
            ->values();
    }

    /**
     * @return Builder<PostMedia>
     */
    private static function profilePhotoMediaQuery(User $profileOwner, ?User $viewer): Builder
    {
        $viewerId = (int) ($viewer?->getKey() ?? 0);
        $visiblePostIds = self::applyProfileTimelineVisibility(
            self::query(),
            $profileOwner,
            $viewer,
        )->select('posts.id');

        return PostMedia::query()
            ->where('post_media.media_type', 'image')
            ->whereIn('post_media.post_id', $visiblePostIds)
            ->with([
                'post' => function ($postQuery) use ($viewer, $viewerId): void {
                    $postQuery
                        ->profileTimelineColumns()
                        ->with([
                            'user',
                            'author.media',
                            'hashtags',
                            'media',
                            'postMedia',
                            'pet' => fn ($petQuery) => $petQuery->visibleTo($viewer),
                        ])
                        ->withListEngagement($viewerId);
                },
            ]);
    }

    /**
     * @return Collection<int, mixed>
     */
    public function mediaItemsForDisplay(): Collection
    {
        $dbMediaItems = $this->relationLoaded('postMedia') ? $this->postMedia->values() : collect();

        if ($dbMediaItems->isNotEmpty()) {
            return $dbMediaItems;
        }

        $spatiePhotos = collect($this->getMedia('photos'))->merge($this->getMedia('images'));
        $spatieVideos = collect($this->getMedia('videos'))->merge($this->getMedia('video'));

        return $spatiePhotos->merge($spatieVideos)->values();
    }

    public static function mediaItemIsVideo(mixed $item): bool
    {
        if (is_object($item) && isset($item->mime_type)) {
            return str_starts_with((string) $item->mime_type, 'video/');
        }

        return is_object($item) && (($item->media_type ?? 'image') === 'video');
    }

    public static function mediaItemIsPhoto(mixed $item): bool
    {
        if (is_object($item) && isset($item->mime_type)) {
            return str_starts_with((string) $item->mime_type, 'image/');
        }

        if (is_object($item) && isset($item->collection_name)) {
            return in_array((string) $item->collection_name, ['photos', 'images'], true);
        }

        return is_object($item) && (($item->media_type ?? 'image') === 'image');
    }

    public static function mediaItemUrl(mixed $item): string
    {
        if (is_object($item) && method_exists($item, 'getUrl')) {
            return (string) $item->getUrl();
        }

        if (is_object($item) && method_exists($item, 'url')) {
            return (string) $item->url();
        }

        return '';
    }

    public static function mediaItemBlurhash(mixed $item): ?string
    {
        $customProperties = self::mediaItemCustomProperties($item);
        $blurhash = $customProperties['blurhash'] ?? $customProperties['blur_hash'] ?? null;

        return is_string($blurhash) && $blurhash !== '' ? $blurhash : null;
    }

    public static function mediaItemPlaceholder(mixed $item): ?string
    {
        $customProperties = self::mediaItemCustomProperties($item);
        $placeholder = $customProperties['blurhash_placeholder']
            ?? $customProperties['placeholder']
            ?? $customProperties['placeholder_data_uri']
            ?? null;

        if (! is_string($placeholder) || $placeholder === '') {
            return null;
        }

        return str_starts_with($placeholder, 'data:image/') ? $placeholder : null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function mediaItemCustomProperties(mixed $item): array
    {
        if (! is_object($item) || ! isset($item->custom_properties)) {
            return [];
        }

        $properties = $item->custom_properties;

        return is_array($properties) ? $properties : [];
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

    public static function paginateMainFeedResults(
        User $viewer,
        ?string $type = null,
        int $perPage = 15,
        ?string $source = null,
        ?string $cursor = null,
        string $ranking = self::FEED_RANKING_LATEST,
        ?int $fromPostId = null
    ): CursorPaginator {
        $viewerId = (int) $viewer->getKey();

        return self::query()
            ->forFeed($viewerId, $source)
            ->withFeedRelations($viewer)
            ->when($type !== null, fn (Builder $query) => $query->byType($type))
            ->when($fromPostId !== null, fn (Builder $query) => $query->atOrOlderThanPost((int) $fromPostId))
            ->orderForMainFeed($ranking)
            ->cursorPaginate($perPage, ['posts.*'], 'cursor', $cursor)
            ->withQueryString();
    }

    /**
     * @param  list<int>  $postIds
     * @return Collection<int, self>
     */
    public static function mainFeedPostsByIds(User $viewer, array $postIds, ?string $type = null, ?string $source = null): Collection
    {
        if ($postIds === []) {
            return collect();
        }

        $posts = self::query()
            ->forFeed((int) $viewer->getKey(), $source)
            ->withFeedRelations($viewer)
            ->when($type !== null, fn (Builder $query) => $query->byType($type))
            ->whereIn('posts.id', $postIds)
            ->get()
            ->keyBy(fn (self $post): int => (int) $post->getKey());

        return collect($postIds)
            ->map(fn (int $postId): ?self => $posts->get($postId))
            ->filter()
            ->values();
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

    public function scopeForFeed(Builder $query, int $userId, ?string $source = null): Builder
    {
        return $query
            ->select(['posts.*'])
            ->whereIn('posts.id', self::feedPostIdsSubquery($userId, $source))
            ->published()
            ->where(function (Builder $visibilityQuery) use ($userId): void {
                $visibilityQuery
                    ->where('posts.user_id', $userId)
                    ->orWhereIn('posts.visibility', [self::VISIBILITY_PUBLIC, self::VISIBILITY_FOLLOWERS])
                    ->orWhere(function (Builder $friendsQuery) use ($userId): void {
                        $friendsQuery
                            ->where('posts.visibility', self::VISIBILITY_FRIENDS)
                            ->whereExists(function (QueryBuilder $mutualQuery) use ($userId): void {
                                $mutualQuery
                                    ->selectRaw('1')
                                    ->from('follows as feed_outer_mutual_follows')
                                    ->whereColumn('feed_outer_mutual_follows.follower_id', 'posts.user_id')
                                    ->where('feed_outer_mutual_follows.following_id', $userId)
                                    ->where('feed_outer_mutual_follows.status', 'accepted');
                            });
                    });
            })
            ->whereHas('author', function (Builder $authorQuery): void {
                User::applyAvailableForProfiles($authorQuery);
            })
            ->whereNull('posts.group_id')
            ->whereNotIn('posts.user_id', Block::query()->select('blocks.blocked_id')->where('blocks.blocker_id', $userId))
            ->whereNotIn('posts.user_id', Block::query()->select('blocks.blocker_id')->where('blocks.blocked_id', $userId))
            ->withoutFeedMutes($userId);
    }

    private static function feedPostIdsSubquery(int $userId, ?string $source): QueryBuilder
    {
        $source = in_array($source, ['people', 'pets'], true) ? $source : null;
        $branches = [
            self::precomputedFeedPostIdsQuery($userId, $source),
        ];

        if ($source !== 'pets') {
            $branches[] = self::ownFeedPostIdsQuery($userId);
            $branches[] = self::followedUserFeedPostIdsQuery($userId);
        }

        if ($source !== 'people') {
            array_push($branches, ...self::followedPetFeedPostIdsQueries($userId));
        }

        $union = array_shift($branches) ?? self::ownFeedPostIdsQuery($userId)->whereRaw('1 = 0');

        foreach ($branches as $branch) {
            $union->union($branch);
        }

        return DB::query()
            ->fromSub($union, 'feed_post_ids')
            ->select('feed_post_ids.id');
    }

    private static function precomputedFeedPostIdsQuery(int $userId, ?string $source): QueryBuilder
    {
        return DB::table('feed_items')
            ->select('feed_items.post_id as id')
            ->where('feed_items.user_id', $userId)
            ->when($source === 'people', function (QueryBuilder $query): void {
                $query->whereIn('feed_items.source_type', [
                    FeedItem::SOURCE_SELF,
                    FeedItem::SOURCE_USER,
                ]);
            })
            ->when($source === 'pets', function (QueryBuilder $query): void {
                $query->where('feed_items.source_type', FeedItem::SOURCE_PET);
            });
    }

    private static function ownFeedPostIdsQuery(int $userId): QueryBuilder
    {
        return self::baseFeedPostIdsQuery()
            ->where('posts.user_id', $userId);
    }

    private static function followedUserFeedPostIdsQuery(int $userId): QueryBuilder
    {
        $query = self::baseFeedPostIdsQuery()
            ->join('follows as feed_author_follows', function ($join) use ($userId): void {
                $join
                    ->on('feed_author_follows.following_id', '=', 'posts.user_id')
                    ->where('feed_author_follows.follower_id', $userId)
                    ->where('feed_author_follows.status', 'accepted');
            });

        return self::applyFollowedAuthorVisibility($query, $userId);
    }

    /**
     * @return list<QueryBuilder>
     */
    private static function followedPetFeedPostIdsQueries(int $userId): array
    {
        $taggedPetPosts = self::baseFeedPostIdsQuery()
            ->join('pet_post as feed_pet_posts', 'feed_pet_posts.post_id', '=', 'posts.id')
            ->join('pet_followers as feed_tagged_pet_followers', function ($join) use ($userId): void {
                $join
                    ->on('feed_tagged_pet_followers.pet_id', '=', 'feed_pet_posts.pet_id')
                    ->where('feed_tagged_pet_followers.user_id', $userId);
            })
            ->where('posts.user_id', '!=', $userId);

        $legacyPetPosts = self::baseFeedPostIdsQuery()
            ->join('pet_followers as feed_legacy_pet_followers', function ($join) use ($userId): void {
                $join
                    ->on('feed_legacy_pet_followers.pet_id', '=', 'posts.pet_id')
                    ->where('feed_legacy_pet_followers.user_id', $userId);
            })
            ->where('posts.user_id', '!=', $userId);

        return [
            self::applyFollowedAuthorVisibility($taggedPetPosts, $userId),
            self::applyFollowedAuthorVisibility($legacyPetPosts, $userId),
        ];
    }

    private static function baseFeedPostIdsQuery(): QueryBuilder
    {
        return DB::table('posts')
            ->select('posts.id')
            ->whereNull('posts.deleted_at')
            ->whereNull('posts.group_id')
            ->where('posts.status', PostStatus::Published->value)
            ->where(function (QueryBuilder $publishedQuery): void {
                $publishedQuery
                    ->whereNull('posts.published_at')
                    ->orWhere('posts.published_at', '<=', now());
            });
    }

    private static function applyFollowedAuthorVisibility(QueryBuilder $query, int $userId): QueryBuilder
    {
        return $query->where(function (QueryBuilder $visibilityQuery) use ($userId): void {
            $visibilityQuery
                ->whereIn('posts.visibility', [self::VISIBILITY_PUBLIC, self::VISIBILITY_FOLLOWERS])
                ->orWhere(function (QueryBuilder $friendsQuery) use ($userId): void {
                    $friendsQuery
                        ->where('posts.visibility', self::VISIBILITY_FRIENDS)
                        ->whereExists(function (QueryBuilder $mutualQuery) use ($userId): void {
                            $mutualQuery
                                ->selectRaw('1')
                                ->from('follows as feed_mutual_follows')
                                ->whereColumn('feed_mutual_follows.follower_id', 'posts.user_id')
                                ->where('feed_mutual_follows.following_id', $userId)
                                ->where('feed_mutual_follows.status', 'accepted');
                        });
                });
        });
    }

    public function scopeWithoutFeedMutes(Builder $query, int $userId): Builder
    {
        $userMorphClass = (new User)->getMorphClass();
        $petMorphClass = (new Pet)->getMorphClass();

        return $query
            ->whereNotExists(function ($muteQuery) use ($userId, $userMorphClass): void {
                $muteQuery
                    ->selectRaw('1')
                    ->from('feed_mutes')
                    ->where('feed_mutes.user_id', $userId)
                    ->where('feed_mutes.mutable_type', $userMorphClass)
                    ->whereColumn('feed_mutes.mutable_id', 'posts.user_id');
            })
            ->where(function (Builder $legacyPetQuery) use ($userId, $petMorphClass): void {
                $legacyPetQuery
                    ->whereNull('posts.pet_id')
                    ->orWhereNotExists(function ($muteQuery) use ($userId, $petMorphClass): void {
                        $muteQuery
                            ->selectRaw('1')
                            ->from('feed_mutes')
                            ->where('feed_mutes.user_id', $userId)
                            ->where('feed_mutes.mutable_type', $petMorphClass)
                            ->whereColumn('feed_mutes.mutable_id', 'posts.pet_id');
                    });
            })
            ->whereDoesntHave('pets', function (Builder $petsQuery) use ($userId, $petMorphClass): void {
                $petsQuery->whereExists(function ($muteQuery) use ($userId, $petMorphClass): void {
                    $muteQuery
                        ->selectRaw('1')
                        ->from('feed_mutes')
                        ->where('feed_mutes.user_id', $userId)
                        ->where('feed_mutes.mutable_type', $petMorphClass)
                        ->whereColumn('feed_mutes.mutable_id', 'pets.id');
                });
            });
    }

    public function scopeAtOrOlderThanPost(Builder $query, int $postId): Builder
    {
        $anchor = self::query()
            ->select(['posts.id', 'posts.created_at'])
            ->whereKey($postId)
            ->first();

        if (! $anchor instanceof self || $anchor->created_at === null) {
            return $query;
        }

        return $query->where(function (Builder $positionQuery) use ($anchor): void {
            $positionQuery
                ->where('posts.created_at', '<', $anchor->created_at)
                ->orWhere(function (Builder $tieQuery) use ($anchor): void {
                    $tieQuery
                        ->where('posts.created_at', $anchor->created_at)
                        ->where('posts.id', '<=', (int) $anchor->getKey());
                });
        });
    }

    public function scopeOrderForMainFeed(Builder $query, string $ranking): Builder
    {
        if ($ranking === self::FEED_RANKING_BEST) {
            return $query
                ->selectRaw(self::feedRankingScoreExpression().' as feed_rank_score')
                ->orderByDesc('feed_rank_score')
                ->orderByDesc('posts.created_at')
                ->orderByDesc('posts.id');
        }

        return $query
            ->orderByDesc('posts.created_at')
            ->orderByDesc('posts.id');
    }

    private static function feedRankingScoreExpression(): string
    {
        $driver = DB::connection()->getDriverName();
        $mediaBonus = "case when posts.type in ('photo', 'video') or exists (select 1 from post_media where post_media.post_id = posts.id and post_media.deleted_at is null) then 250 else 0 end";

        if ($driver === 'sqlite') {
            $recencyScore = "max(0, 1000000 - ((strftime('%s', 'now') - strftime('%s', posts.created_at)) / 60.0))";
        } else {
            $recencyScore = 'greatest(0, 1000000 - (timestampdiff(minute, posts.created_at, utc_timestamp())))';
        }

        return "({$recencyScore}) + (coalesce(posts.reactions_count, posts.likes_count, 0) * 75) + (coalesce(posts.comments_count, 0) * 120) + {$mediaBonus}";
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
                    ->where(function (Builder $petFollowQuery) use ($userId): void {
                        $petFollowQuery
                            ->whereHas('pet.followers', fn (Builder $followersQuery): Builder => $followersQuery->where('users.id', $userId))
                            ->orWhereHas('pets.followers', fn (Builder $followersQuery): Builder => $followersQuery->where('users.id', $userId));
                    });
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
                'pets',
                'media',
                'tags',
                'originalPost.author.media',
                'originalPost.postMedia',
                'quotePost.author.media',
                'quotePost.postMedia',
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
            ->withCurrentViewerReaction($viewerId)
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

        return $query
            ->withCurrentViewerReaction($viewerId)
            ->withExists([
                'reactions as liked_by_viewer' => fn (Builder $reactionQuery) => $reactionQuery
                    ->where('reactions.user_id', $viewerId),
                'savedBy as saved_by_viewer' => fn (Builder $saveQuery): Builder => $saveQuery
                    ->where('saved_posts.user_id', $viewerId),
            ]);
    }

    public function scopeWithCurrentViewerReaction(Builder $query, ?int $viewerId): Builder
    {
        $viewerId = $viewerId ?? 0;

        if ($viewerId <= 0) {
            return $query;
        }

        return $query->addSelect([
            'current_user_reaction_type' => Reaction::query()
                ->select('type')
                ->where('reactions.reactable_type', (new self)->getMorphClass())
                ->whereColumn('reactions.reactable_id', 'posts.id')
                ->where('reactions.user_id', $viewerId)
                ->limit(1),
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
