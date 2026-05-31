<?php

namespace App\Models\Activities;

use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

#[Fillable([
    'organizer_user_id',
    'title',
    'slug',
    'description',
    'prize',
    'species',
    'starts_at',
    'ends_at',
    'max_entries',
    'entries_count',
    'winner_entry_id',
    'status',
])]
class Contest extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;
    use SoftDeletes;

    /** @var list<string> */
    public const STATUSES = ['draft', 'active', 'voting', 'ended', 'cancelled'];

    /** @var array<string, list<string>> */
    public const TRANSITIONS = [
        'draft' => ['active'],
        'active' => ['voting', 'cancelled'],
        'voting' => ['ended', 'cancelled'],
    ];

    public const MEDIA_COLLECTION_COVER = 'cover';

    public const MEDIA_CONVERSION_THUMB = 'thumb';

    public const MEDIA_CONVERSION_AVATAR = 'avatar';

    public const MEDIA_CONVERSION_CARD = 'card';

    public const MEDIA_CONVERSION_PREVIEW = 'preview';

    public const MEDIA_CONVERSION_LARGE = 'large';

    public const MEDIA_CONVERSION_COVER = 'cover';

    public const MEDIA_COLLECTION_ALLOWLIST_IMAGE = ['image/jpeg', 'image/png', 'image/webp'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'max_entries' => 'integer',
            'entries_count' => 'integer',
            'winner_entry_id' => 'integer',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::MEDIA_COLLECTION_COVER)
            ->singleFile()
            ->acceptsMimeTypes(self::MEDIA_COLLECTION_ALLOWLIST_IMAGE);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion(self::MEDIA_CONVERSION_THUMB)
            ->fit(Fit::Crop, 150, 150)
            ->format('webp')
            ->quality(80)
            ->performOnCollections(self::MEDIA_COLLECTION_COVER)
            ->nonQueued();

        $this->addMediaConversion(self::MEDIA_CONVERSION_AVATAR)
            ->fit(Fit::Crop, 240, 240)
            ->format('webp')
            ->quality(82)
            ->performOnCollections(self::MEDIA_COLLECTION_COVER);

        $this->addMediaConversion(self::MEDIA_CONVERSION_CARD)
            ->width(520)
            ->format('webp')
            ->quality(84)
            ->performOnCollections(self::MEDIA_COLLECTION_COVER);

        $this->addMediaConversion(self::MEDIA_CONVERSION_PREVIEW)
            ->width(900)
            ->format('webp')
            ->quality(85)
            ->performOnCollections(self::MEDIA_COLLECTION_COVER);

        $this->addMediaConversion(self::MEDIA_CONVERSION_LARGE)
            ->width(1400)
            ->format('webp')
            ->quality(88)
            ->performOnCollections(self::MEDIA_COLLECTION_COVER);

        $this->addMediaConversion(self::MEDIA_CONVERSION_COVER)
            ->fit(Fit::Crop, 1400, 500)
            ->format('webp')
            ->quality(84)
            ->performOnCollections(self::MEDIA_COLLECTION_COVER);
    }

    protected static function booted(): void
    {
        static::creating(function (self $contest): void {
            if (! $contest->slug && $contest->title) {
                $contest->slug = static::generateUniqueSlug((string) $contest->title);
            }
        });
    }

    public static function generateUniqueSlug(string $seed): string
    {
        $base = Str::slug($seed) ?: 'contest';
        $slug = $base;
        $suffix = 1;

        while (static::query()->where('slug', $slug)->exists()) {
            $suffix++;
            $slug = "{$base}-{$suffix}";
        }

        return $slug;
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_user_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(ContestEntry::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(ContestVote::class);
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(ContestEntry::class, 'winner_entry_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeVoting(Builder $query): Builder
    {
        return $query->where('status', 'voting');
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->whereIn('status', ['active', 'voting', 'ended']);
    }

    public function hasEntered(User $user): bool
    {
        return $this->entries()->where('user_id', $user->id)->exists();
    }

    public function hasVoted(User $user): bool
    {
        return $this->votes()->where('user_id', $user->id)->exists();
    }

    public function isActive(): bool
    {
        return $this->status === 'active'
            && now()->between($this->starts_at, $this->ends_at);
    }
}
