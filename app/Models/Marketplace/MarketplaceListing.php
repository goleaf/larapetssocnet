<?php

namespace App\Models\Marketplace;

use App\Models\Identity\User;
use App\Models\Messaging\Message;
use App\Models\Moderation\Report;
use App\Models\Pets\Pet;
use App\Support\Search\SearchInput;
use App\Traits\HasCounterCache;
use Database\Factories\MarketplaceListingFactory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

#[UseFactory(MarketplaceListingFactory::class)]
#[Appends([
    'cover_photo_url',
    'formatted_price',
])]
#[Fillable([
    'user_id',
    'pet_id',
    'title',
    'description',
    'listing_type',
    'status',
    'price',
    'currency',
    'location_text',
    'contact_phone',
    'contact_email',
    'views_count',
])]
class MarketplaceListing extends Model implements HasMedia
{
    use HasCounterCache;
    use HasFactory;
    use InteractsWithMedia;
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SOLD = 'sold';

    public const STATUS_ARCHIVED = 'archived';

    public const MEDIA_COLLECTION_COVER = 'cover';

    public const MEDIA_COLLECTION_GALLERY = 'gallery';

    public const MEDIA_COLLECTION_LISTING_IMAGES = 'listing-images';

    public const MEDIA_COLLECTION_IMAGES = 'images';

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
            'price' => 'decimal:2',
            'views_count' => 'integer',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::MEDIA_COLLECTION_COVER)
            ->singleFile()
            ->acceptsMimeTypes(self::MEDIA_COLLECTION_ALLOWLIST_IMAGE);

        $this->addMediaCollection(self::MEDIA_COLLECTION_LISTING_IMAGES)
            ->acceptsMimeTypes(self::MEDIA_COLLECTION_ALLOWLIST_IMAGE);

        $this->addMediaCollection(self::MEDIA_COLLECTION_GALLERY)
            ->acceptsMimeTypes(self::MEDIA_COLLECTION_ALLOWLIST_IMAGE);

        $this->addMediaCollection(self::MEDIA_COLLECTION_IMAGES)
            ->acceptsMimeTypes(self::MEDIA_COLLECTION_ALLOWLIST_IMAGE);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion(self::MEDIA_CONVERSION_THUMB)
            ->fit(Fit::Crop, 140, 140)
            ->format('webp')
            ->quality(80)
            ->performOnCollections(self::MEDIA_COLLECTION_COVER, self::MEDIA_COLLECTION_LISTING_IMAGES, self::MEDIA_COLLECTION_GALLERY, self::MEDIA_COLLECTION_IMAGES)
            ->nonQueued();

        $this->addMediaConversion(self::MEDIA_CONVERSION_AVATAR)
            ->fit(Fit::Crop, 320, 320)
            ->format('webp')
            ->quality(82)
            ->performOnCollections(self::MEDIA_COLLECTION_LISTING_IMAGES, self::MEDIA_COLLECTION_GALLERY, self::MEDIA_COLLECTION_IMAGES);

        $this->addMediaConversion(self::MEDIA_CONVERSION_CARD)
            ->width(600)
            ->format('webp')
            ->quality(84)
            ->performOnCollections(self::MEDIA_COLLECTION_LISTING_IMAGES, self::MEDIA_COLLECTION_GALLERY, self::MEDIA_COLLECTION_IMAGES);

        $this->addMediaConversion(self::MEDIA_CONVERSION_PREVIEW)
            ->width(1000)
            ->format('webp')
            ->quality(85)
            ->performOnCollections(self::MEDIA_COLLECTION_LISTING_IMAGES, self::MEDIA_COLLECTION_GALLERY, self::MEDIA_COLLECTION_IMAGES);

        $this->addMediaConversion(self::MEDIA_CONVERSION_LARGE)
            ->width(1600)
            ->format('webp')
            ->quality(88)
            ->performOnCollections(self::MEDIA_COLLECTION_COVER, self::MEDIA_COLLECTION_LISTING_IMAGES, self::MEDIA_COLLECTION_GALLERY, self::MEDIA_COLLECTION_IMAGES);

        $this->addMediaConversion(self::MEDIA_CONVERSION_COVER)
            ->fit(Fit::Crop, 1400, 520)
            ->format('webp')
            ->quality(84)
            ->performOnCollections(self::MEDIA_COLLECTION_COVER, self::MEDIA_COLLECTION_LISTING_IMAGES, self::MEDIA_COLLECTION_GALLERY, self::MEDIA_COLLECTION_IMAGES);
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
        return $query;
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = SearchInput::normalize($term);

        if ($term === '') {
            return $query;
        }

        $pattern = SearchInput::containsPattern($term);

        return $query->where(function (Builder $subQuery) use ($pattern): void {
            $subQuery
                ->where('title', 'like', $pattern)
                ->orWhere('description', 'like', $pattern)
                ->orWhere('location_text', 'like', $pattern);
        });
    }

    public function scopeOfType(Builder $query, ?string $listingType): Builder
    {
        if (! $listingType) {
            return $query;
        }

        return $query->where('listing_type', $listingType);
    }

    public function scopeForSeller(Builder $query, User $seller): Builder
    {
        return $query->where('user_id', $seller->getKey());
    }

    public static function createForSeller(User $seller, array $attributes): self
    {
        return self::query()->create([
            ...$attributes,
            'user_id' => $seller->getKey(),
        ]);
    }

    public static function findByIdOrFail(int $listingId, bool $withTrashed = false): self
    {
        $query = self::query();

        if ($withTrashed) {
            $query->withTrashed();
        }

        return $query->findOrFail($listingId);
    }

    /**
     * @return Collection<int, string>
     */
    public static function listingTypeOptions(): Collection
    {
        return self::query()
            ->select('listing_type')
            ->whereNotNull('listing_type')
            ->distinct()
            ->orderBy('listing_type')
            ->pluck('listing_type')
            ->filter()
            ->values();
    }

    /**
     * @param  array{
     *     q?: string,
     *     listing_type?: string,
     *     status?: string,
     *     min_price?: float|int|string|null,
     *     max_price?: float|int|string|null,
     *     location?: string,
     *     sort?: string
     * }  $filters
     */
    public static function paginatePublicCatalog(array $filters, int $perPage = 12): LengthAwarePaginator
    {
        $query = self::query()
            ->with(['seller:id,name,username,avatar_path'])
            ->search($filters['q'] ?? '')
            ->ofType(trim((string) ($filters['listing_type'] ?? '')));

        $status = static::normalizePublicStatusFilter((string) ($filters['status'] ?? ''));
        $query->where('status', $status);

        $minPrice = $filters['min_price'] ?? null;
        if ($minPrice !== null && $minPrice !== '') {
            $query->where('price', '>=', (float) $minPrice);
        }

        $maxPrice = $filters['max_price'] ?? null;
        if ($maxPrice !== null && $maxPrice !== '') {
            $query->where('price', '<=', (float) $maxPrice);
        }

        $location = SearchInput::normalize($filters['location'] ?? '');
        if ($location !== '') {
            $query->where('location_text', 'like', SearchInput::containsPattern($location));
        }

        static::applySort($query, (string) ($filters['sort'] ?? 'newest'));

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * @param  array{
     *     q?: string,
     *     status?: string,
     *     sort?: string
     * }  $filters
     */
    public static function paginateForSellerDashboard(User $seller, array $filters, int $perPage = 12): LengthAwarePaginator
    {
        $query = self::query()
            ->forSeller($seller)
            ->with(['pet:id,name'])
            ->search($filters['q'] ?? '');

        $status = trim(strtolower((string) ($filters['status'] ?? '')));
        if ($status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        static::applySort($query, (string) ($filters['sort'] ?? 'newest'));

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * @param  array{
     *     q?: string,
     *     status?: string,
     *     sort?: string
     * }  $filters
     */
    public static function paginateManagedBySeller(User $seller, array $filters, int $perPage = 12): LengthAwarePaginator
    {
        $query = self::query()
            ->withTrashed()
            ->forSeller($seller)
            ->with(['pet:id,name'])
            ->search($filters['q'] ?? '');

        $status = trim(strtolower((string) ($filters['status'] ?? '')));

        if ($status === 'deleted') {
            $query->onlyTrashed();
        } elseif ($status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        static::applySort($query, (string) ($filters['sort'] ?? 'newest'));

        return $query->paginate($perPage)->withQueryString();
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
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

            $image = $this->getFirstMediaUrl('gallery');

            if ($image !== '') {
                return $image;
            }

            $legacyImage = $this->getFirstMediaUrl('images');

            if ($legacyImage !== '') {
                return $legacyImage;
            }

            return '';
        });
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

    protected static function normalizePublicStatusFilter(string $status): string
    {
        return in_array($status, [self::STATUS_ACTIVE, self::STATUS_SOLD], true)
            ? $status
            : self::STATUS_ACTIVE;
    }

    protected static function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            'oldest' => $query->oldest('created_at'),
            'price_low' => $query->orderBy('price'),
            'price_high' => $query->orderByDesc('price'),
            'most_viewed' => $query->orderByDesc('views_count'),
            default => $query->latest('created_at'),
        };
    }
}
