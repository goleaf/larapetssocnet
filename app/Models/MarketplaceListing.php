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

    /**
     * @var list<string>
     */
    protected $fillable = [
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
    ];

    /**
     * @var list<string>
     */
    protected $appends = [
        'cover_photo_url',
        'formatted_price',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'views_count' => 'integer',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('cover')->singleFile();
        $this->addMediaCollection('gallery');
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
        return $query;
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
                ->orWhere('location_text', 'like', "%{$term}%");
        });
    }

    public function scopeOfType(Builder $query, ?string $listingType): Builder
    {
        if (! $listingType) {
            return $query;
        }

        return $query->where('listing_type', $listingType);
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
}
