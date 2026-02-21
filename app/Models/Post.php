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

    public const TYPE_IMAGE = 'image';

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
        'metadata',
        'published_at',
        'is_pinned',
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
            'published_at' => 'datetime',
            'is_pinned' => 'boolean',
            'comments_count' => 'integer',
            'reactions_count' => 'integer',
            'shares_count' => 'integer',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('images');
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
        } elseif ($this->getMedia('images')->isNotEmpty()) {
            $newType = self::TYPE_IMAGE;
        }

        if ($this->type !== $newType) {
            $this->updateQuietly(['type' => $newType]);
        }
    }

    protected function coverPhotoUrl(): Attribute
    {
        return Attribute::get(function (): string {
            $imageUrl = $this->getFirstMediaUrl('images');

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
}
