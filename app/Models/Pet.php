<?php

namespace App\Models;

use App\Services\PetVisibilityService;
use App\Traits\HasCounterCache;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

class Pet extends Model implements HasMedia
{
    use HasCounterCache;
    use HasFactory;
    use InteractsWithMedia;
    use SoftDeletes;

    public const SPECIES = ['dog', 'cat', 'bird', 'fish', 'rabbit', 'hamster', 'reptile', 'other'];

    public const GENDERS = ['male', 'female', 'unknown'];

    public const SIZES = ['small', 'medium', 'large', 'xlarge'];

    public const ADOPTION_STATUSES = ['not_listed', 'available', 'pending', 'adopted'];

    public const MEDIA_COLLECTION_AVATAR = 'avatar';

    public const MEDIA_COLLECTION_COVER = 'cover';

    public const MEDIA_COLLECTION_GALLERY = 'gallery';

    public const MEDIA_CONVERSION_AVATAR_THUMB = 'avatar_thumb';

    public const MEDIA_CONVERSION_AVATAR_SMALL = 'avatar_small';

    public const MEDIA_CONVERSION_AVATAR_MEDIUM = 'avatar_medium';

    public const MEDIA_CONVERSION_GALLERY_THUMB = 'gallery_thumb';

    public const MEDIA_CONVERSION_GALLERY_MEDIUM = 'gallery_medium';

    public const SPECIES_EMOJI = [
        'dog' => '🐕',
        'cat' => '🐈',
        'bird' => '🐦',
        'fish' => '🐠',
        'rabbit' => '🐰',
        'hamster' => '🐹',
        'reptile' => '🦎',
        'other' => '🐾',
    ];

    /**
     * @var array<string, bool>
     */
    protected static array $petsColumnsCache = [];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'species',
        'breed',
        'sex',
        'gender',
        'size',
        'birth_date',
        'date_of_birth',
        'age_text',
        'adopted_at',
        'bio',
        'bio_html',
        'personality_tags',
        'color',
        'weight_kg',
        'is_public',
        'is_lost',
        'is_deceased',
        'is_adoptable',
        'adoption_status',
        'adoption_fee',
        'adoption_notes',
        'adoption_contact',
        'adoption_listed_at',
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

    /**
     * @var list<string>
     */
    protected $with = [
        'user',
        'species',
        'breed',
        'media',
        'tags',
    ];

    /**
     * @var list<string>
     */
    protected $withCount = [
        'posts',
        'followers',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'date_of_birth' => 'date',
            'birthdate' => 'date',
            'adopted_at' => 'date',
            'adoption_listed_at' => 'datetime',
            'personality_tags' => 'array',
            'is_public' => 'boolean',
            'is_lost' => 'boolean',
            'is_deceased' => 'boolean',
            'is_adoptable' => 'boolean',
            'weight_kg' => 'decimal:2',
            'followers_count' => 'integer',
            'posts_count' => 'integer',
            'health_logs_count' => 'integer',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::MEDIA_COLLECTION_AVATAR)
            ->singleFile()
            ->useDisk('public')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif']);
        $this->addMediaCollection(self::MEDIA_COLLECTION_COVER)
            ->singleFile()
            ->useDisk('public')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif']);
        $this->addMediaCollection(self::MEDIA_COLLECTION_GALLERY)
            ->useDisk('public')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif']);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion(self::MEDIA_CONVERSION_AVATAR_THUMB)
            ->fit(Fit::Crop, 80, 80)
            ->format('webp')
            ->quality(80)
            ->performOnCollections(self::MEDIA_COLLECTION_AVATAR)
            ->nonQueued();

        $this->addMediaConversion(self::MEDIA_CONVERSION_AVATAR_SMALL)
            ->fit(Fit::Crop, 150, 150)
            ->format('webp')
            ->quality(80)
            ->performOnCollections(self::MEDIA_COLLECTION_AVATAR)
            ->nonQueued();

        $this->addMediaConversion(self::MEDIA_CONVERSION_AVATAR_MEDIUM)
            ->fit(Fit::Crop, 400, 400)
            ->format('webp')
            ->quality(85)
            ->performOnCollections(self::MEDIA_COLLECTION_AVATAR)
            ->nonQueued();

        $this->addMediaConversion(self::MEDIA_CONVERSION_GALLERY_THUMB)
            ->fit(Fit::Crop, 150, 150)
            ->format('webp')
            ->quality(80)
            ->performOnCollections(self::MEDIA_COLLECTION_GALLERY)
            ->nonQueued();

        $this->addMediaConversion(self::MEDIA_CONVERSION_GALLERY_MEDIUM)
            ->width(800)
            ->format('webp')
            ->quality(85)
            ->performOnCollections(self::MEDIA_COLLECTION_GALLERY)
            ->nonQueued();
    }

    public function galleryMedia(): MorphMany
    {
        return $this->media()
            ->where('collection_name', self::MEDIA_COLLECTION_GALLERY)
            ->orderBy('order_column');
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

    public function species(): BelongsTo
    {
        return $this->belongsTo(Species::class, 'species', 'slug');
    }

    public function breed(): BelongsTo
    {
        return $this->belongsTo(Breed::class, 'breed', 'name');
    }

    public function tags(): HasMany
    {
        return $this->hasMany(PetTag::class);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        $slug = Str::slug($term);

        return $query->where(function (Builder $subQuery) use ($term, $slug): void {
            $subQuery
                ->where('name', 'like', "%{$term}%")
                ->orWhere('species', 'like', "%{$term}%")
                ->orWhere('breed', 'like', "%{$term}%")
                ->orWhereHas('tags', function (Builder $tagQuery) use ($term, $slug): void {
                    $tagQuery->where('name', 'like', "%{$term}%");

                    if ($slug !== '') {
                        $tagQuery->orWhere('slug', 'like', "%{$slug}%");
                    }
                });
        });
    }

    public function scopeSearchResultColumns(Builder $query): Builder
    {
        return $query->select([
            'pets.id',
            'pets.user_id',
            'pets.name',
            'pets.species',
            'pets.breed',
            'pets.bio',
            'pets.created_at',
        ]);
    }

    public static function paginateSearchResults(?User $viewer, string $term, int $perPage = 15): LengthAwarePaginator
    {
        return self::query()
            ->searchResultColumns()
            ->visibleTo($viewer)
            ->search($term !== '' ? $term : null)
            ->latest('pets.created_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array{
     *     q?: string,
     *     species?: string,
     *     breed?: string,
     *     sex?: string,
     *     personality_tags?: list<string>,
     *     is_adoptable?: bool|null,
     *     sort?: string
     * }  $filters
     */
    public static function paginateExploreCatalog(array $filters, ?User $viewer = null, int $perPage = 12): LengthAwarePaginator
    {
        $query = self::query()
            ->visibleTo($viewer)
            ->public()
            ->with('owner:id,name');

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $innerQuery) use ($search): void {
                foreach (['name', 'bio', 'breed', 'species'] as $column) {
                    if (self::hasPetsColumn($column)) {
                        $innerQuery->orWhere($column, 'like', "%{$search}%");
                    }
                }
            });
        }

        foreach (['species', 'breed', 'sex'] as $column) {
            $value = trim((string) ($filters[$column] ?? ''));

            if ($value !== '' && self::hasPetsColumn($column)) {
                $query->where($column, $value);
            }
        }

        if (! empty($filters['personality_tags']) && is_array($filters['personality_tags'])) {
            $query->withAnyPersonalityTags($filters['personality_tags']);
        }

        if (array_key_exists('is_adoptable', $filters) && $filters['is_adoptable'] !== null) {
            $adoptable = (bool) $filters['is_adoptable'];

            if (self::hasPetsColumn('is_adoptable')) {
                $query->where('is_adoptable', $adoptable);
            } elseif (self::hasPetsColumn('is_for_adoption')) {
                $query->where('is_for_adoption', $adoptable);
            }
        }

        self::applyCatalogSort($query, (string) ($filters['sort'] ?? 'newest'), true);

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * @param  array{
     *     q?: string,
     *     species?: string,
     *     sex?: string,
     *     personality_tags?: list<string>,
     *     sort?: string
     * }  $filters
     */
    public static function paginateAdoptionCatalog(array $filters, ?User $viewer = null, int $perPage = 12): LengthAwarePaginator
    {
        $query = self::query()
            ->visibleTo($viewer)
            ->public()
            ->with('owner:id,name');

        if (self::hasPetsColumn('is_adoptable')) {
            $query->where('is_adoptable', true);
        } elseif (self::hasPetsColumn('is_for_adoption')) {
            $query->where('is_for_adoption', true);
        }

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $innerQuery) use ($search): void {
                foreach (['name', 'bio', 'breed'] as $column) {
                    if (self::hasPetsColumn($column)) {
                        $innerQuery->orWhere($column, 'like', "%{$search}%");
                    }
                }
            });
        }

        foreach (['species', 'sex'] as $column) {
            $value = trim((string) ($filters[$column] ?? ''));

            if ($value !== '' && self::hasPetsColumn($column)) {
                $query->where($column, $value);
            }
        }

        if (! empty($filters['personality_tags']) && is_array($filters['personality_tags'])) {
            $query->withAnyPersonalityTags($filters['personality_tags']);
        }

        self::applyCatalogSort($query, (string) ($filters['sort'] ?? 'newest'), false);

        return $query->paginate($perPage)->withQueryString();
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query
            ->select(['pets.*'])
            ->where(fn (Builder $subQuery) => $subQuery->whereNull('is_public')->orWhere('is_public', true));
    }

    public function scopeVisibleTo(Builder $query, ?User $viewer): Builder
    {
        return app(PetVisibilityService::class)->applyVisibleScope($query, $viewer);
    }

    public function scopeLost(Builder $query): Builder
    {
        return $query->where('is_lost', true);
    }

    public function scopeAdoptable(Builder $query): Builder
    {
        return $query->where('is_adoptable', true);
    }

    public function scopeWithPersonalityTag(Builder $query, string $tag): Builder
    {
        $slug = Str::slug($tag);

        if ($slug === '') {
            return $query;
        }

        return $query
            ->select(['pets.*'])
            ->whereHas('tags', fn (Builder $tagQuery) => $tagQuery->where('slug', $slug));
    }

    /**
     * @param  list<string>  $tags
     */
    public function scopeWithAnyPersonalityTags(Builder $query, array $tags): Builder
    {
        $slugs = collect($tags)
            ->map(static fn (string $tag): string => Str::slug($tag))
            ->filter()
            ->unique()
            ->values();

        if ($slugs->isEmpty()) {
            return $query;
        }

        return $query
            ->select(['pets.*'])
            ->whereHas('tags', fn (Builder $tagQuery) => $tagQuery->whereIn('slug', $slugs->all()));
    }

    public function scopeBySpecies(Builder $query, string|int $speciesId): Builder
    {
        return $query
            ->select(['pets.*'])
            ->where('pets.species', (string) $speciesId);
    }

    public function scopeByBreed(Builder $query, string|int $breedId): Builder
    {
        return $query
            ->select(['pets.*'])
            ->where('pets.breed', (string) $breedId);
    }

    public function scopeOwnedBy(Builder $query, User|int $userId): Builder
    {
        $resolvedUserId = $userId instanceof User ? (int) $userId->getKey() : (int) $userId;

        return $query
            ->select(['pets.*'])
            ->where('pets.user_id', $resolvedUserId);
    }

    public function scopeAvailableForAdoption(Builder $query): Builder
    {
        return $query->where('adoption_status', 'available');
    }

    public function isFollowedBy(User $user): bool
    {
        return $this->followers()->whereKey($user->getKey())->exists();
    }

    public static function resolveForRoute(string $slug): ?self
    {
        $query = self::query();

        if (self::hasPetsColumn('slug')) {
            $query->where('slug', $slug)->orWhere('id', $slug);
        } else {
            $query->where('id', $slug);
        }

        return $query->first();
    }

    public function resolveRouteBinding($value, $field = null): ?Model
    {
        $bindingField = $field ?? $this->getRouteKeyName();

        if ($bindingField === 'slug') {
            return static::query()
                ->where('slug', $value)
                ->orWhere($this->getQualifiedKeyName(), $value)
                ->first();
        }

        return parent::resolveRouteBinding($value, $field);
    }

    public function isOwnedBy(?Authenticatable $user): bool
    {
        if (! $user) {
            return false;
        }

        $ownerId = $this->getAttribute('user_id') ?? $this->getAttribute('owner_id');

        return (int) $ownerId === (int) $user->getAuthIdentifier();
    }

    /**
     * @return Collection<int, Post>
     */
    public function recentPostsForShow(int $limit = 12): Collection
    {
        return $this->posts()
            ->with([
                'user',
                'author',
                'author.media',
                'pet',
                'pet.media',
                'media',
                'postMedia',
                'likes',
                'comments.user',
            ])
            ->withCount([
                'comments',
                'likes',
            ])
            ->latest('posts.created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, mixed>
     */
    public function galleryForShow(int $limit = 24): Collection
    {
        if (! method_exists($this, 'getMedia')) {
            return collect();
        }

        $this->loadMissing('media');

        return collect($this->getMedia(self::MEDIA_COLLECTION_GALLERY))
            ->sortBy(function ($media): string {
                $order = (int) ($media->order_column ?? 0);
                $timestamp = (int) (optional($media->created_at)->timestamp ?? 0);

                return sprintf('%05d-%010d', $order, $timestamp);
            })
            ->take($limit)
            ->values();
    }

    /**
     * @return Collection<int, PetHealthLog>
     */
    public function recentHealthLogs(int $limit = 12): Collection
    {
        return $this->healthLogs()
            ->latest('logged_at')
            ->limit($limit)
            ->get();
    }

    public function followedBy(User $user): bool
    {
        return $user->followPet($this);
    }

    public function unfollowedBy(User $user): bool
    {
        return $user->unfollowPet($this);
    }

    public function getAvatarUrl(): string
    {
        return (string) ($this->avatar_url ?: '/images/default-avatar.png');
    }

    protected function avatarUrl(): Attribute
    {
        return Attribute::get(function (): string {
            $mediaUrl = $this->getFirstMediaUrl(self::MEDIA_COLLECTION_AVATAR);

            if ($mediaUrl !== '') {
                return $mediaUrl;
            }

            return (string) ($this->avatar_path ?: '/images/default-avatar.png');
        });
    }

    protected function coverPhotoUrl(): Attribute
    {
        return Attribute::get(function (): string {
            $mediaUrl = $this->getFirstMediaUrl(self::MEDIA_COLLECTION_COVER);

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
            $birthDate = $this->getAttribute('birth_date');

            if (! $birthDate) {
                return null;
            }

            return $birthDate->age;
        });
    }

    protected function ageFormatted(): Attribute
    {
        return Attribute::get(function (): ?string {
            $birthDate = $this->getAttribute('date_of_birth') ?? $this->getAttribute('birth_date');

            if (! $birthDate) {
                return $this->getAttribute('age_text');
            }

            $diff = now()->diff($birthDate);
            if ($diff->y > 0) {
                return $diff->y.' years';
            }

            if ($diff->m > 0) {
                return $diff->m.' months';
            }

            return $diff->d.' days';
        });
    }

    protected function speciesEmoji(): Attribute
    {
        return Attribute::get(fn (): string => self::SPECIES_EMOJI[(string) $this->getAttribute('species')] ?? self::SPECIES_EMOJI['other']);
    }

    protected function isAvailableForAdoption(): Attribute
    {
        return Attribute::get(fn (): bool => $this->getAttribute('adoption_status') === 'available');
    }

    /**
     * Weight-type health logs ordered oldest to newest (for charting).
     */
    public function getWeightLogsAttribute(): \Illuminate\Support\Collection
    {
        return $this->healthLogs()
            ->where('log_type', PetHealthLog::TYPE_WEIGHT)
            ->oldest('logged_at')
            ->get();
    }

    /**
     * Health logs with an upcoming next_due_at within the next 30 days.
     */
    public function getUpcomingRemindersAttribute(): \Illuminate\Support\Collection
    {
        return $this->healthLogs()
            ->whereNotNull('next_due_at')
            ->where('next_due_at', '>=', today())
            ->where('next_due_at', '<=', today()->addDays(30))
            ->oldest('next_due_at')
            ->get();
    }

    /**
     * True if any health log reminder is due within the next 7 days.
     */
    public function getHasUrgentRemindersAttribute(): bool
    {
        return $this->healthLogs()
            ->whereNotNull('next_due_at')
            ->where('next_due_at', '>=', today())
            ->where('next_due_at', '<=', today()->addDays(7))
            ->exists();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected static function applyCatalogSort(Builder $query, string $sort, bool $allowWeightSort): void
    {
        match ($sort) {
            'oldest' => $query->oldest('created_at'),
            'name_asc' => $query->orderBy('name'),
            'name_desc' => $query->orderByDesc('name'),
            'weight_desc' => self::applyWeightSort($query, $allowWeightSort),
            default => $query->latest('created_at'),
        };
    }

    protected static function applyWeightSort(Builder $query, bool $allowWeightSort): void
    {
        if (! $allowWeightSort) {
            $query->latest('created_at');

            return;
        }

        if (self::hasPetsColumn('weight')) {
            $query->orderByDesc('weight');

            return;
        }

        if (self::hasPetsColumn('weight_kg')) {
            $query->orderByDesc('weight_kg');

            return;
        }

        $query->latest('created_at');
    }

    protected static function hasPetsColumn(string $column): bool
    {
        if (! array_key_exists($column, static::$petsColumnsCache)) {
            try {
                static::$petsColumnsCache[$column] = Schema::hasColumn('pets', $column);
            } catch (Throwable) {
                static::$petsColumnsCache[$column] = false;
            }
        }

        return static::$petsColumnsCache[$column];
    }
}
