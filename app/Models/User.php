<?php

namespace App\Models;

use App\Traits\HasCounterCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements HasMedia
{
    use HasCounterCache;
    use HasFactory;
    use HasRoles;
    use InteractsWithMedia;
    use Notifiable;

    /**
     * @var array<string, bool>
     */
    protected static array $usersColumnsCache = [];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'bio',
        'location',
        'website',
        'birth_date',
        'interests_text',
        'is_private',
        'onboarding_step',
        'onboarding_completed_at',
        'last_seen_at',
        'avatar_path',
        'cover_photo_path',
        'profile_photo_path',
        'followers_count',
        'following_count',
        'following_pets_count',
        'pets_count',
        'posts_count',
        'blocked_users_count',
        'blocked_by_count',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
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
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'birth_date' => 'date',
            'is_private' => 'boolean',
            'onboarding_completed_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'followers_count' => 'integer',
            'following_count' => 'integer',
            'following_pets_count' => 'integer',
            'pets_count' => 'integer',
            'posts_count' => 'integer',
            'blocked_users_count' => 'integer',
            'blocked_by_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $user): void {
            if (! static::hasUsersColumn('username')) {
                return;
            }

            $normalizedUsername = (string) Str::of((string) $user->username)
                ->lower()
                ->replaceMatches('/[^a-z0-9._]/', '')
                ->trim('._');

            if ($normalizedUsername !== '') {
                $user->username = static::generateUniqueUsername($normalizedUsername);

                return;
            }

            $seed = $user->name ?: Str::before((string) $user->email, '@');
            $user->username = static::generateUniqueUsername($seed);
        });
    }

    public static function generateUniqueUsername(string $seed): string
    {
        $base = (string) Str::of($seed)
            ->lower()
            ->replaceMatches('/[^a-z0-9._]/', '')
            ->trim('._');

        if ($base === '') {
            $base = 'petlover';
        }

        $base = Str::limit($base, 24, '');
        $username = $base;
        $suffix = 0;

        if (! static::hasUsersColumn('username')) {
            return $username;
        }

        while (static::query()->where('username', $username)->exists()) {
            $suffix++;
            $suffixText = (string) $suffix;
            $username = Str::limit($base, 30 - strlen($suffixText) - 1, '').'_'.$suffixText;
        }

        return $username;
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('avatar')->singleFile();
        $this->addMediaCollection('cover')->singleFile();
        $this->addMediaCollection('profile')->singleFile();
    }

    public function pets(): HasMany
    {
        return $this->hasMany(Pet::class);
    }

    public function followedPets(): BelongsToMany
    {
        return $this->belongsToMany(Pet::class, 'pet_followers', 'user_id', 'pet_id')
            ->withTimestamps();
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(Reaction::class);
    }

    public function ownedGroups(): HasMany
    {
        return $this->hasMany(Group::class, 'owner_user_id');
    }

    public function groupMemberships(): HasMany
    {
        return $this->hasMany(GroupMember::class);
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'group_members', 'user_id', 'group_id')
            ->withPivot(['role', 'status', 'joined_at'])
            ->withTimestamps();
    }

    public function createdEvents(): HasMany
    {
        return $this->hasMany(Event::class, 'creator_user_id');
    }

    public function eventAttendances(): HasMany
    {
        return $this->hasMany(EventAttendee::class);
    }

    public function attendingEvents(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_attendees', 'user_id', 'event_id')
            ->withPivot(['status', 'responded_at', 'checked_in_at'])
            ->withTimestamps();
    }

    public function marketplaceListings(): HasMany
    {
        return $this->hasMany(MarketplaceListing::class);
    }

    public function sentMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_user_id');
    }

    public function receivedMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'recipient_user_id');
    }

    public function unreadMessagesCount(): int
    {
        return (int) $this->receivedMessages()
            ->unread()
            ->count();
    }

    public function unreadThreadsCount(): int
    {
        return (int) $this->receivedMessages()
            ->unread()
            ->distinct('sender_user_id')
            ->count('sender_user_id');
    }

    public function filedReports(): HasMany
    {
        return $this->hasMany(Report::class, 'reporter_user_id');
    }

    public function reportsAgainst(): HasMany
    {
        return $this->hasMany(Report::class, 'reportable_id')
            ->where('reportable_type', self::class);
    }

    public function resolvedReports(): HasMany
    {
        return $this->hasMany(Report::class, 'reviewed_by_user_id');
    }

    public function petHealthLogs(): HasMany
    {
        return $this->hasMany(PetHealthLog::class, 'logged_by_user_id');
    }

    public function petsHealthLogs(): HasManyThrough
    {
        return $this->hasManyThrough(PetHealthLog::class, Pet::class);
    }

    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'user_follows', 'following_id', 'follower_id')
            ->withTimestamps();
    }

    public function following(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'user_follows', 'follower_id', 'following_id')
            ->withTimestamps();
    }

    public function blockedUsers(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'user_blocks', 'blocker_id', 'blocked_id')
            ->withTimestamps();
    }

    public function blockedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'user_blocks', 'blocked_id', 'blocker_id')
            ->withTimestamps();
    }

    public function badges(): BelongsToMany
    {
        return $this->belongsToMany(Badge::class, 'badge_user')
            ->withPivot('awarded_at')
            ->withTimestamps();
    }

    public function contestEntries(): HasMany
    {
        return $this->hasMany(ContestEntry::class);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        return $query->where(function (Builder $subQuery) use ($term): void {
            $subQuery
                ->where('name', 'like', "%{$term}%")
                ->orWhere('username', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('city', 'like', "%{$term}%");
        });
    }

    public function scopeDiscoverable(Builder $query): Builder
    {
        return $query->where(fn (Builder $subQuery) => $subQuery->whereNull('is_private')->orWhere('is_private', false));
    }

    public function scopeActiveRecently(Builder $query, int $days = 30): Builder
    {
        return $query->where('last_seen_at', '>=', now()->subDays($days));
    }

    public function isFollowing(self $user): bool
    {
        return $this->following()->whereKey($user->getKey())->exists();
    }

    public function isFollowedBy(self $user): bool
    {
        return $this->followers()->whereKey($user->getKey())->exists();
    }

    public function follow(self $user): bool
    {
        if ($this->is($user) || $this->hasBlocked($user) || $user->hasBlocked($this)) {
            return false;
        }

        return DB::transaction(function () use ($user): bool {
            $alreadyFollowing = $this->following()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->exists();

            if ($alreadyFollowing) {
                return false;
            }

            $this->following()->attach($user->getKey(), [
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->incrementCounter('following_count');
            $user->incrementCounter('followers_count');

            return true;
        });
    }

    public function unfollow(self $user): bool
    {
        if ($this->is($user)) {
            return false;
        }

        return DB::transaction(function () use ($user): bool {
            $detached = $this->following()->detach($user->getKey()) > 0;

            if (! $detached) {
                return false;
            }

            $this->decrementCounter('following_count');
            $user->decrementCounter('followers_count');

            return true;
        });
    }

    public function hasBlocked(self $user): bool
    {
        return $this->blockedUsers()->whereKey($user->getKey())->exists();
    }

    public function isBlockedBy(self $user): bool
    {
        return $this->blockedByUsers()->whereKey($user->getKey())->exists();
    }

    public function block(self $user): bool
    {
        return $this->blockUser($user);
    }

    public function unblock(self $user): bool
    {
        return $this->unblockUser($user);
    }

    public function blockUser(self $user): bool
    {
        if ($this->is($user)) {
            return false;
        }

        return DB::transaction(function () use ($user): bool {
            $alreadyBlocked = $this->blockedUsers()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->exists();

            if ($alreadyBlocked) {
                return false;
            }

            $this->blockedUsers()->attach($user->getKey(), [
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->incrementCounter('blocked_users_count');
            $user->incrementCounter('blocked_by_count');

            if ($this->isFollowing($user)) {
                $this->following()->detach($user->getKey());
                $this->decrementCounter('following_count');
                $user->decrementCounter('followers_count');
            }

            if ($user->isFollowing($this)) {
                $user->following()->detach($this->getKey());
                $user->decrementCounter('following_count');
                $this->decrementCounter('followers_count');
            }

            return true;
        });
    }

    public function unblockUser(self $user): bool
    {
        return DB::transaction(function () use ($user): bool {
            $detached = $this->blockedUsers()->detach($user->getKey()) > 0;

            if (! $detached) {
                return false;
            }

            $this->decrementCounter('blocked_users_count');
            $user->decrementCounter('blocked_by_count');

            return true;
        });
    }

    public function followPet(Pet $pet): bool
    {
        return DB::transaction(function () use ($pet): bool {
            $alreadyFollowing = $this->followedPets()
                ->whereKey($pet->getKey())
                ->lockForUpdate()
                ->exists();

            if ($alreadyFollowing) {
                return false;
            }

            $this->followedPets()->attach($pet->getKey(), [
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->incrementCounter('following_pets_count');
            $pet->incrementCounter('followers_count');

            return true;
        });
    }

    public function unfollowPet(Pet $pet): bool
    {
        return DB::transaction(function () use ($pet): bool {
            $detached = $this->followedPets()->detach($pet->getKey()) > 0;

            if (! $detached) {
                return false;
            }

            $this->decrementCounter('following_pets_count');
            $pet->decrementCounter('followers_count');

            return true;
        });
    }

    public function isFollowingPet(Pet $pet): bool
    {
        return $this->followedPets()->whereKey($pet->getKey())->exists();
    }

    public function canView(Model $model): bool
    {
        if (! $model instanceof self) {
            return true;
        }

        if ($model->hasBlocked($this) || $this->hasBlocked($model)) {
            return false;
        }

        if (! $model->is_private) {
            return true;
        }

        return $model->is($this) || $this->isFollowing($model);
    }

    public function setAttribute($key, $value)
    {
        if (is_string($key) && $this->shouldIgnoreMissingColumn($key)) {
            return $this;
        }

        return parent::setAttribute($key, $value);
    }

    protected function shouldIgnoreMissingColumn(string $key): bool
    {
        if (str_contains($key, '->')) {
            return false;
        }

        if ($this->hasSetMutator($key) || $this->hasAttributeSetMutator($key)) {
            return false;
        }

        if (method_exists($this, $key)) {
            return false;
        }

        return ! static::hasUsersColumn($key);
    }

    protected static function hasUsersColumn(string $column): bool
    {
        if (! isset(static::$usersColumnsCache[$column])) {
            static::$usersColumnsCache[$column] = Schema::hasColumn('users', $column);
        }

        return static::$usersColumnsCache[$column];
    }

    protected function avatarUrl(): Attribute
    {
        return Attribute::get(function (): string {
            $mediaUrl = $this->getFirstMediaUrl('avatar');

            if ($mediaUrl !== '') {
                return $mediaUrl;
            }

            if (! empty($this->avatar_path)) {
                return (string) $this->avatar_path;
            }

            if (! empty($this->profile_photo_path)) {
                return (string) $this->profile_photo_path;
            }

            return 'https://ui-avatars.com/api/?name='.urlencode((string) $this->name);
        });
    }

    protected function coverPhotoUrl(): Attribute
    {
        return Attribute::get(function (): string {
            $mediaUrl = $this->getFirstMediaUrl('cover');

            if ($mediaUrl !== '') {
                return $mediaUrl;
            }

            if (! empty($this->cover_photo_path)) {
                return (string) $this->cover_photo_path;
            }

            return $this->avatar_url;
        });
    }

    protected function profilePhotoUrl(): Attribute
    {
        return Attribute::get(fn (): string => $this->getFirstMediaUrl('profile') ?: $this->avatar_url);
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
