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

    public const TYPE_IMAGE = self::TYPE_PHOTO;

    public const TYPE_VIDEO = 'video';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'pet_id',
        'group_id',
        'body',
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
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'tagged_pets' => 'array',
            'published_at' => 'datetime',
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
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif']);

        // Keep legacy collection for compatibility with older records.
        $this->addMediaCollection('images')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif']);

        $this->addMediaCollection('video')->singleFile();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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

    public function reports(): MorphMany
    {
        return $this->morphMany(Report::class, 'reportable');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where(function (Builder $subQuery): void {
            $subQuery
                ->whereNull('status')
                ->orWhere('status', 'published');
        })->where(function (Builder $subQuery): void {
            $subQuery
                ->whereNull('published_at')
                ->orWhere('published_at', '<=', now());
        });
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query->where('visibility', self::VISIBILITY_PUBLIC);
    }

    public function scopeForFeed(Builder $query, ?User $viewer = null): Builder
    {
        $query->published();

        if (! $viewer) {
            return $query->public();
        }

        return $query->where(function (Builder $subQuery) use ($viewer): void {
            $subQuery
                ->where('visibility', self::VISIBILITY_PUBLIC)
                ->orWhere(function (Builder $followerQuery) use ($viewer): void {
                    $followerQuery
                        ->where('visibility', self::VISIBILITY_FOLLOWERS)
                        ->whereIn('user_id', $viewer->following()->select('users.id'));
                })
                ->orWhere('user_id', $viewer->getKey());
        });
    }

    public function scopeNotBlockedFor(Builder $query, ?User $viewer): Builder
    {
        if (! $viewer) {
            return $query;
        }

        return $query
            ->whereNotIn(
                'user_id',
                UserBlock::query()
                    ->select('blocked_id')
                    ->where('blocker_id', $viewer->id)
            )
            ->whereNotIn(
                'user_id',
                UserBlock::query()
                    ->select('blocker_id')
                    ->where('blocked_id', $viewer->id)
            );
    }

    public function scopeVisibleTo(Builder $query, ?User $viewer): Builder
    {
        if (! $viewer) {
            return $query
                ->where('visibility', self::VISIBILITY_PUBLIC)
                ->notBlockedFor($viewer);
        }

        $followingIds = $viewer->following()->select('users.id');

        return $query
            ->where(function (Builder $visibilityQuery) use ($viewer, $followingIds): void {
                $visibilityQuery
                    ->where('user_id', $viewer->id)
                    ->orWhere('visibility', self::VISIBILITY_PUBLIC)
                    ->orWhere(function (Builder $followersQuery) use ($followingIds): void {
                        $followersQuery
                            ->where('visibility', self::VISIBILITY_FOLLOWERS)
                            ->whereIn('user_id', $followingIds);
                    });
            })
            ->notBlockedFor($viewer);
    }

    public function canBeViewedBy(?User $viewer): bool
    {
        if (! $viewer) {
            return $this->visibility === self::VISIBILITY_PUBLIC;
        }

        if ($viewer->getKey() === $this->user_id) {
            return true;
        }

        if ($this->user && ($viewer->hasBlocked($this->user) || $this->user->hasBlocked($viewer))) {
            return false;
        }

        if ($this->visibility === self::VISIBILITY_PUBLIC) {
            return true;
        }

        if ($this->visibility === self::VISIBILITY_FOLLOWERS) {
            return $viewer->isFollowing($this->user);
        }

        return false;
    }

    public function syncHashtagsFromBody(): void
    {
        preg_match_all('/#([\pL\pN_]+)/u', (string) $this->body, $matches);

        $names = collect($matches[1] ?? [])
            ->map(fn (string $name): string => Str::lower($name))
            ->filter()
            ->unique()
            ->values();

        if ($names->isEmpty()) {
            $this->hashtags()->detach();

            return;
        }

        $hashtagIds = $names
            ->map(fn (string $name): int => Hashtag::firstOrCreate(['name' => $name])->getKey());

        $this->hashtags()->sync($hashtagIds->all());
    }

    public function updateTypeFromMedia(): void
    {
        $newType = self::TYPE_TEXT;

        if ($this->getMedia('video')->isNotEmpty()) {
            $newType = self::TYPE_VIDEO;
        } elseif ($this->getMedia('photos')->isNotEmpty() || $this->getMedia('images')->isNotEmpty()) {
            $newType = self::TYPE_PHOTO;
        }

        if ($this->type !== $newType) {
            $this->updateQuietly(['type' => $newType]);
        }
    }

    protected function coverPhotoUrl(): Attribute
    {
        return Attribute::get(function (): string {
            $imageUrl = $this->getFirstMediaUrl('photos') ?: $this->getFirstMediaUrl('images');

            if ($imageUrl !== '') {
                return $imageUrl;
            }

            $videoUrl = $this->getFirstMediaUrl('video');

            if ($videoUrl !== '') {
                return $videoUrl;
            }

            return '';
        });
    }

    protected function excerpt(): Attribute
    {
        return Attribute::get(fn (): string => Str::limit(strip_tags((string) $this->body), 140));
    }

    public function refreshLikesCount(): void
    {
        $count = $this->postReactions()->count();

        $this->updateQuietly([
            'likes_count' => $count,
            'reactions_count' => $count,
        ]);
    }

    public function refreshCommentsCount(): void
    {
        $this->updateQuietly([
            'comments_count' => $this->comments()->count(),
        ]);
    }

    /**
     * @return Collection<int, string>
     */
    public static function visibilityOptions(): Collection
    {
        return collect([
            self::VISIBILITY_PUBLIC,
            self::VISIBILITY_FOLLOWERS,
            self::VISIBILITY_PRIVATE,
        ]);
    }
}
