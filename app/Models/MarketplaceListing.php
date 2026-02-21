<?php

namespace App\Models;

use App\Traits\HasCounterCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class MarketplaceListing extends Model implements HasMedia
{
    use HasCounterCache;
    use HasFactory;
    use HasSlug;
    use InteractsWithMedia;
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SOLD = 'sold';

    public const STATUS_ARCHIVED = 'archived';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'pet_id',
        'title',
        'slug',
        'description',
        'category',
        'status',
        'price',
        'currency',
        'is_negotiable',
        'location',
        'metadata',
        'published_at',
        'expires_at',
        'cover_photo_path',
        'views_count',
        'favorites_count',
    ];

    /**
     * @var list<string>
     */
    protected $appends = [
        'cover_photo_url',
        'avatar_url',
        'profile_photo_url',
        'formatted_price',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_negotiable' => 'boolean',
            'metadata' => 'array',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
            'views_count' => 'integer',
            'favorites_count' => 'integer',
        ];
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('title')
            ->saveSlugsTo('slug');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('cover')->singleFile();
        $this->addMediaCollection('images');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function user(): BelongsTo
    {
        return $this->seller();
    }

    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'marketplace_listing_id');
    }

    public function reports(): MorphMany
    {
        return $this->morphMany(Report::class, 'reportable');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where(function (Builder $subQuery): void {
            $subQuery
                ->whereNull('published_at')
                ->orWhere('published_at', '<=', now());
        });
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        return $query->where(function (Builder $subQuery) use ($term): void {
            $subQuery
                ->where('title', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%")
                ->orWhere('location', 'like', "%{$term}%");
        });
    }

    public function scopeInCategory(Builder $query, ?string $category): Builder
    {
        if (! $category) {
            return $query;
        }

        return $query->where('category', $category);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function isActive(): bool
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    public function bumpViews(): void
    {
        $this->incrementCounter('views_count');
    }

    protected function coverPhotoUrl(): Attribute
    {
        return Attribute::get(function (): string {
            $cover = $this->getFirstMediaUrl('cover');

            if ($cover !== '') {
                return $cover;
            }

            $image = $this->getFirstMediaUrl('images');

            if ($image !== '') {
                return $image;
            }

            return (string) ($this->cover_photo_path ?: '');
        });
    }

    protected function avatarUrl(): Attribute
    {
        return Attribute::get(fn (): string => $this->cover_photo_url);
    }

    protected function profilePhotoUrl(): Attribute
    {
        return Attribute::get(fn (): string => $this->cover_photo_url);
    }

    protected function formattedPrice(): Attribute
    {
        return Attribute::get(function (): string {
            if ($this->price === null) {
                return '';
            }

            $currency = strtoupper((string) ($this->currency ?: 'USD'));

            return number_format((float) $this->price, 2).' '.$currency;
        });
    }
}
