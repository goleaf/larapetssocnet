<?php

namespace App\Models;

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
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
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

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'date_of_birth' => 'date',
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
        $this->addMediaCollection('avatar')->singleFile()->useDisk('public');
        $this->addMediaCollection('cover')->singleFile();
        $this->addMediaCollection('gallery')->useDisk('public');
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

    public static function paginateSearchResults(string $term, int $perPage = 15): LengthAwarePaginator
    {
        return self::query()
            ->searchResultColumns()
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
     *     is_adoptable?: bool|null,
     *     sort?: string
     * }  $filters
     */
    public static function paginateExploreCatalog(array $filters, int $perPage = 12): LengthAwarePaginator
    {
        $query = self::query()->with('owner:id,name');

        if (self::hasPetsColumn('is_public')) {
            $query->where('is_public', true);
        }

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
     *     sort?: string
     * }  $filters
     */
    public static function paginateAdoptionCatalog(array $filters, int $perPage = 12): LengthAwarePaginator
    {
        $query = self::query()->with('owner:id,name');

        if (self::hasPetsColumn('is_public')) {
            $query->where('is_public', true);
        }

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

        self::applyCatalogSort($query, (string) ($filters['sort'] ?? 'newest'), false);

        return $query->paginate($perPage)->withQueryString();
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

    public function scopeBySpecies(Builder $query, string $species): Builder
    {
        return $query->where('species', $species);
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
        if (method_exists($this, 'getMedia')) {
            return collect($this->getMedia('gallery'))
                ->sortByDesc(fn ($media) => $media->created_at)
                ->take($limit)
                ->values();
        }

        if (method_exists($this, 'galleryItems')) {
            return $this->galleryItems()
                ->latest()
                ->limit($limit)
                ->get();
        }

        return collect();
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
            $mediaUrl = $this->getFirstMediaUrl('avatar');

            if ($mediaUrl !== '') {
                return $mediaUrl;
            }

            return (string) ($this->avatar_path ?: '/images/default-avatar.png');
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
            $birthDate = $this->date_of_birth ?? $this->birth_date;
            if (! $birthDate) {
                return $this->age_text;
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
        return Attribute::get(fn (): string => self::SPECIES_EMOJI[$this->species] ?? self::SPECIES_EMOJI['other']);
    }

    protected function isAvailableForAdoption(): Attribute
    {
        return Attribute::get(fn (): bool => $this->adoption_status === 'available');
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
