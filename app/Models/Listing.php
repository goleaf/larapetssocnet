<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Listing extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'pet_id',
        'title',
        'slug',
        'description',
        'listing_type',
        'status',
        'price',
        'currency',
        'price_negotiable',
        'location_text',
        'contact_phone',
        'contact_email',
        'views_count',
        'published_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'price_negotiable' => 'boolean',
            'views_count' => 'integer',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ListingImage::class)->orderBy('order')->orderBy('id');
    }

    public function coverImage(): HasOne
    {
        return $this->hasOne(ListingImage::class)
            ->where('is_cover', true)
            ->orderBy('order')
            ->orderBy('id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeForUser(Builder $query, User|int $user): Builder
    {
        $userId = $user instanceof User ? (int) $user->getKey() : (int) $user;

        return $query->where('user_id', $userId);
    }

    public function scopeByType(Builder $query, ?string $type): Builder
    {
        if (blank($type)) {
            return $query;
        }

        return $query->where('listing_type', $type);
    }

    public function scopeByStatus(Builder $query, ?string $status): Builder
    {
        if (blank($status)) {
            return $query;
        }

        return $query->where('status', $status);
    }

    public function formattedPrice(): string
    {
        if ($this->price === null) {
            return $this->price_negotiable ? 'Negotiable' : '';
        }

        $currency = strtoupper((string) ($this->currency ?: 'USD'));

        return number_format((float) $this->price, 2).' '.$currency;
    }

    public function coverImageUrl(): string
    {
        $cover = $this->coverImage()->first();

        if ($cover) {
            return (string) $cover->url;
        }

        return (string) ($this->images()->first()?->url ?? '');
    }

    public static function generateUniqueSlug(string $seed, ?int $ignoreId = null): string
    {
        $base = Str::slug($seed);

        if ($base === '') {
            $base = 'listing';
        }

        $slug = $base;
        $suffix = 1;

        while (
            static::query()
                ->when($ignoreId !== null, fn (Builder $query) => $query->whereKeyNot($ignoreId))
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    public function isOwner(User $user): bool
    {
        return (int) $this->user_id === (int) $user->getKey();
    }

    public function statusLabel(): string
    {
        return match ((string) $this->status) {
            'active' => 'Active',
            'draft' => 'Draft',
            'sold' => 'Sold',
            'archived' => 'Archived',
            default => Str::of((string) ($this->status ?: 'unknown'))->replace('_', ' ')->title()->toString(),
        };
    }

    public function statusColor(): string
    {
        return match ((string) $this->status) {
            'active' => 'green',
            'draft' => 'gray',
            'sold' => 'blue',
            'archived' => 'slate',
            default => 'zinc',
        };
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
