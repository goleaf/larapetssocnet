<?php

namespace App\Models;

use App\Services\BlockService;
use App\Services\FollowService;
use App\Traits\HasCounterCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements HasMedia
{
    use HasCounterCache;
    use HasFactory;
    use HasRoles;
    use InteractsWithMedia;
    use Notifiable;

    public const MEDIA_COLLECTION_AVATAR = 'avatar';

    public const MEDIA_COLLECTION_COVER = 'cover';

    public const MEDIA_COLLECTION_PROFILE = 'profile';

    public const MEDIA_COLLECTION_PHOTOS = 'photos';

    public const MEDIA_CONVERSION_AVATAR_THUMB = 'avatar_thumb';

    public const MEDIA_CONVERSION_AVATAR_CARD = 'avatar_card';

    public const MEDIA_CONVERSION_COVER_BANNER = 'cover_banner';

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
        'bio_html',
        'location',
        'website',
        'birth_date',
        'city',
        'country_code',
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
        'follow_requests_count',
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
            'follow_requests_count' => 'integer',
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

            $normalizedUsername = static::normalizeUsername((string) $user->username);

            if ($normalizedUsername !== '') {
                $user->username = static::generateUniqueUsername($normalizedUsername);

                return;
            }

            $seed = $user->name ?: Str::before((string) $user->email, '@');
            $user->username = static::generateUniqueUsername($seed);
        });
    }

    public static function normalizeUsername(?string $username): string
    {
        return (string) Str::of((string) $username)
            ->lower()
            ->replaceMatches('/[^a-z0-9._]/', '')
            ->trim('._');
    }

    public static function generateUniqueUsername(string $seed): string
    {
        $base = static::normalizeUsername($seed);

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

    public static function isUsernameAvailable(string $username, ?self $ignore = null): bool
    {
        if (! static::hasUsersColumn('username')) {
            return false;
        }

        $normalized = static::normalizeUsername($username);

        if (strlen($normalized) < 3) {
            return false;
        }

        $query = static::query()->where('username', $normalized);

        if ($ignore?->exists) {
            $query->whereKeyNot($ignore->getKey());
        }

        return ! $query->exists();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::MEDIA_COLLECTION_AVATAR)->singleFile();
        $this->addMediaCollection(self::MEDIA_COLLECTION_COVER)->singleFile();
        $this->addMediaCollection(self::MEDIA_COLLECTION_PROFILE)->singleFile();
        $this->addMediaCollection(self::MEDIA_COLLECTION_PHOTOS);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion(self::MEDIA_CONVERSION_AVATAR_THUMB)
            ->fit(Fit::Crop, 96, 96)
            ->performOnCollections(self::MEDIA_COLLECTION_AVATAR)
            ->nonQueued();

        $this->addMediaConversion(self::MEDIA_CONVERSION_AVATAR_CARD)
            ->fit(Fit::Crop, 256, 256)
            ->performOnCollections(self::MEDIA_COLLECTION_AVATAR)
            ->nonQueued();

        $this->addMediaConversion(self::MEDIA_CONVERSION_COVER_BANNER)
            ->fit(Fit::Crop, 1600, 480)
            ->performOnCollections(self::MEDIA_COLLECTION_COVER)
            ->nonQueued();
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
        return $this->belongsToMany(self::class, 'follows', 'following_id', 'follower_id')
            ->using(Follow::class)
            ->withPivot(['status', 'created_at']);
    }

    public function following(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'follows', 'follower_id', 'following_id')
            ->using(Follow::class)
            ->withPivot(['status', 'created_at']);
    }

    public function acceptedFollowers(): BelongsToMany
    {
        return $this->followers()->wherePivot('status', 'accepted');
    }

    public function acceptedFollowing(): BelongsToMany
    {
        return $this->following()->wherePivot('status', 'accepted');
    }

    public function pendingFollowRequests(): BelongsToMany
    {
        return $this->followers()->wherePivot('status', 'pending');
    }

    public function sentPendingRequests(): BelongsToMany
    {
        return $this->following()->wherePivot('status', 'pending');
    }

    public function blocking(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'blocks', 'blocker_id', 'blocked_id')
            ->using(Block::class)
            ->withPivot('created_at');
    }

    public function blockedBy(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'blocks', 'blocked_id', 'blocker_id')
            ->using(Block::class)
            ->withPivot('created_at');
    }

    public function blockedUsers(): BelongsToMany
    {
        return $this->blocking();
    }

    public function blockedByUsers(): BelongsToMany
    {
        return $this->blockedBy();
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

    public function scopeNotBlockedFor(Builder $query, ?self $viewer): Builder
    {
        if (! $viewer) {
            return $query;
        }

        return $query
            ->whereNotIn(
                'users.id',
                $viewer->blocking()->select('users.id')
            )
            ->whereNotIn(
                'users.id',
                $viewer->blockedBy()->select('users.id')
            );
    }

    public function scopeVisibleTo(Builder $query, ?self $viewer): Builder
    {
        if (! $viewer) {
            return $query
                ->discoverable()
                ->notBlockedFor($viewer);
        }

        return $query
            ->where(function (Builder $visibilityQuery) use ($viewer): void {
                $visibilityQuery
                    ->whereKey($viewer->getKey())
                    ->orWhere(fn (Builder $public) => $public->whereNull('is_private')->orWhere('is_private', false))
                    ->orWhere(function (Builder $followers) use ($viewer): void {
                        $followers
                            ->where('is_private', true)
                            ->whereIn('users.id', $viewer->following()->select('users.id'));
                    });
            })
            ->notBlockedFor($viewer);
    }

    public function scopeFollowedBy(Builder $query, self $user): Builder
    {
        return $query->whereIn('id', $user->acceptedFollowing()->pluck('users.id'));
    }

    public function scopeNotFollowedBy(Builder $query, self $user): Builder
    {
        return $query->whereNotIn(
            'id',
            $user->acceptedFollowing()->pluck('users.id')->push($user->getKey())
        );
    }

    public function isFollowing(self $user): bool
    {
        return $this->acceptedFollowing()->whereKey($user->getKey())->exists();
    }

    public function isFollowedBy(self $user): bool
    {
        return $this->acceptedFollowers()->whereKey($user->getKey())->exists();
    }

    public function hasRequestedFollow(self $user): bool
    {
        return $this->sentPendingRequests()->whereKey($user->getKey())->exists();
    }

    public function getFollowStatus(self $user): string
    {
        $row = Follow::query()
            ->where('follower_id', $this->getKey())
            ->where('following_id', $user->getKey())
            ->first();

        return match ($row?->status) {
            'accepted' => 'following',
            'pending' => 'pending',
            default => 'none',
        };
    }

    public function follow(self $user): string
    {
        return app(FollowService::class)->follow($this, $user);
    }

    public function unfollow(self $user): void
    {
        app(FollowService::class)->unfollow($this, $user);
    }

    public function approveFollowRequest(self $requester): void
    {
        app(FollowService::class)->approve($this, $requester);
    }

    public function rejectFollowRequest(self $requester): void
    {
        app(FollowService::class)->reject($this, $requester);
    }

    public function getMutualFollowers(self $other)
    {
        $myFollowerIds = $this->acceptedFollowers()->pluck('users.id');
        $otherFollowerIds = $other->acceptedFollowers()->pluck('users.id');
        $mutualIds = $myFollowerIds->intersect($otherFollowerIds)->take(5);

        return self::query()->whereIn('id', $mutualIds)->with('media')->get();
    }

    public function getSuggestedUsersToFollow(int $limit = 6)
    {
        $excludeIds = $this->acceptedFollowing()
            ->pluck('users.id')
            ->push($this->getKey())
            ->merge($this->blocking()->pluck('users.id'))
            ->merge($this->blockedBy()->pluck('users.id'))
            ->unique();

        return self::query()
            ->whereNotIn('id', $excludeIds)
            ->where('is_banned', false)
            ->where('is_private', false)
            ->orderByDesc('followers_count')
            ->limit($limit)
            ->with('media')
            ->get();
    }

    public function hasBlocked(self $user): bool
    {
        return $this->blocking()->whereKey($user->getKey())->exists();
    }

    public function isBlockedBy(self $user): bool
    {
        return $this->blockedBy()->whereKey($user->getKey())->exists();
    }

    public function hasBlockingRelationshipWith(self $user): bool
    {
        return $this->hasBlocked($user) || $this->isBlockedBy($user);
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
        app(BlockService::class)->block($this, $user);

        return true;
    }

    public function unblockUser(self $user): bool
    {
        app(BlockService::class)->unblock($this, $user);

        return true;
    }

    public function hasAnyBlockRelationship(self $user): bool
    {
        return $this->hasBlocked($user) || $this->isBlockedBy($user);
    }

    public function scopeNotBlockedBy(Builder $query, self $user): Builder
    {
        return $query->whereNotIn('users.id', $user->blocking()->select('users.id'));
    }

    public function scopeNotBlocking(Builder $query, self $user): Builder
    {
        return $query->whereNotIn('users.id', $user->blockedBy()->select('users.id'));
    }

    public function scopeHasNoBlockRelationshipWith(Builder $query, self $user): Builder
    {
        $blockedIds = $user->blocking()->pluck('users.id');
        $blockerIds = $user->blockedBy()->pluck('users.id');
        $excludeIds = $blockedIds->merge($blockerIds)->unique();

        if ($excludeIds->isEmpty()) {
            return $query;
        }

        return $query->whereNotIn('users.id', $excludeIds);
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

    public function canBeViewedBy(?self $viewer): bool
    {
        if (! $viewer) {
            return ! $this->is_private;
        }

        if ($viewer->hasBlockingRelationshipWith($this)) {
            return false;
        }

        if (! $this->is_private) {
            return true;
        }

        return $viewer->is($this) || $viewer->isFollowing($this) || $viewer->hasAnyRole(['admin', 'moderator']);
    }

    public function canViewFollowersList(?self $viewer): bool
    {
        return $this->canBeViewedBy($viewer);
    }

    public function canViewFollowingList(?self $viewer): bool
    {
        return $this->canBeViewedBy($viewer);
    }

    public function canView(Model $model): bool
    {
        if (! $model instanceof self) {
            return true;
        }

        return $model->canBeViewedBy($this);
    }

    public function updateAvatar(UploadedFile $file): void
    {
        $this->storeProfileMedia($file, self::MEDIA_COLLECTION_AVATAR);

        $this->forceFill([
            'avatar_path' => null,
            'profile_photo_path' => null,
        ])->saveQuietly();
    }

    public function updateCover(UploadedFile $file): void
    {
        $this->storeProfileMedia($file, self::MEDIA_COLLECTION_COVER);

        $this->forceFill([
            'cover_photo_path' => null,
        ])->saveQuietly();
    }

    public function setAttribute($key, $value)
    {
        if (is_string($key) && $this->shouldIgnoreMissingColumn($key)) {
            return $this;
        }

        return parent::setAttribute($key, $value);
    }

    protected function lockUsersForRelationship(self $user): void
    {
        static::query()
            ->whereIn($this->getQualifiedKeyName(), [$this->getKey(), $user->getKey()])
            ->lockForUpdate()
            ->get();
    }

    protected function storeProfileMedia(UploadedFile $file, string $collection): void
    {
        $extension = $file->guessExtension() ?: $file->extension() ?: 'jpg';

        $this->addMedia($file)
            ->usingFileName($collection.'-'.Str::uuid().'.'.$extension)
            ->toMediaCollection($collection);
    }

    protected function firstMediaUrl(string $collection, ?string $conversion = null): string
    {
        $media = $this->getFirstMedia($collection);

        if (! $media) {
            return '';
        }

        if ($conversion !== null && $media->hasGeneratedConversion($conversion)) {
            return $media->getUrl($conversion);
        }

        return $media->getUrl();
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
            $mediaUrl = $this->firstMediaUrl(self::MEDIA_COLLECTION_AVATAR, self::MEDIA_CONVERSION_AVATAR_CARD);

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
            $mediaUrl = $this->firstMediaUrl(self::MEDIA_COLLECTION_COVER, self::MEDIA_CONVERSION_COVER_BANNER);

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
        return Attribute::get(fn (): string => $this->firstMediaUrl(self::MEDIA_COLLECTION_PROFILE) ?: $this->avatar_url);
    }

    protected function bioHtml(): Attribute
    {
        return Attribute::get(function (?string $value): ?string {
            if (filled($value)) {
                return (string) $value;
            }

            $bio = trim((string) $this->bio);

            if ($bio === '') {
                return null;
            }

            return nl2br(e($bio));
        });
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
