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
}
