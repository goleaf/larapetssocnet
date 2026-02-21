<?php

namespace App\Models;

use App\Traits\HasCounterCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Pet extends Model implements HasMedia
{
    use HasCounterCache;
    use HasFactory;
    use InteractsWithMedia;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'species',
        'breed',
        'sex',
        'birth_date',
        'adopted_at',
        'bio',
        'color',
        'weight_kg',
        'is_public',
        'is_lost',
        'is_adoptable',
        'avatar_path',
        'cover_photo_path',
        'followers_count',
        'posts_count',
        'health_logs_count',
    ];

    /**
     * @var list<string>
     */
    protected $appends = [
        'avatar_url',
        'cover_photo_url',
        'profile_photo_url',
        'age_formatted',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'adopted_at' => 'date',
            'is_public' => 'boolean',
            'is_lost' => 'boolean',
            'is_adoptable' => 'boolean',
            'weight_kg' => 'decimal:2',
            'followers_count' => 'integer',
            'posts_count' => 'integer',
            'health_logs_count' => 'integer',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('avatar')->singleFile();
        $this->addMediaCollection('cover')->singleFile();
        $this->addMediaCollection('gallery');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function user(): BelongsTo
    {
        return $this->owner();
    }

    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'pet_followers', 'pet_id', 'user_id')
            ->withTimestamps();
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function healthLogs(): HasMany
    {
        return $this->hasMany(PetHealthLog::class);
    }

    public function marketplaceListings(): HasMany
    {
        return $this->hasMany(MarketplaceListing::class);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        return $query->where(function (Builder $subQuery) use ($term): void {
            $subQuery
                ->where('name', 'like', "%{$term}%")
                ->orWhere('species', 'like', "%{$term}%")
                ->orWhere('breed', 'like', "%{$term}%");
        });
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query->where(fn (Builder $subQuery) => $subQuery->whereNull('is_public')->orWhere('is_public', true));
    }

    public function scopeLost(Builder $query): Builder
    {
        return $query->where('is_lost', true);
    }

    public function scopeAdoptable(Builder $query): Builder
    {
        return $query->where('is_adoptable', true);
    }

    public function isFollowedBy(User $user): bool
    {
        return $this->followers()->whereKey($user->getKey())->exists();
    }

    public function followedBy(User $user): bool
    {
        return $user->followPet($this);
    }

    public function unfollowedBy(User $user): bool
    {
        return $user->unfollowPet($this);
    }

    protected function avatarUrl(): Attribute
    {
        return Attribute::get(function (): string {
            $mediaUrl = $this->getFirstMediaUrl('avatar');

            if ($mediaUrl !== '') {
                return $mediaUrl;
            }

            return (string) ($this->avatar_path ?: 'https://ui-avatars.com/api/?name='.urlencode((string) $this->name));
        });
    }

    protected function coverPhotoUrl(): Attribute
    {
        return Attribute::get(function (): string {
            $mediaUrl = $this->getFirstMediaUrl('cover');

            if ($mediaUrl !== '') {
                return $mediaUrl;
            }

            return (string) ($this->cover_photo_path ?: $this->avatar_url);
        });
    }

    protected function profilePhotoUrl(): Attribute
    {
        return Attribute::get(fn (): string => $this->avatar_url);
    }

    protected function ageYears(): Attribute
    {
        return Attribute::get(function (): ?int {
            if (! $this->birth_date) {
                return null;
            }

            return $this->birth_date->age;
        });
    }

    protected function ageFormatted(): Attribute
    {
        return Attribute::get(function (): ?string {
            if ($this->age_years === null) {
                return null;
            }

            return $this->age_years.' years';
        });
    }
}
