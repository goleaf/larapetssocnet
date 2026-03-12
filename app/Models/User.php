<?php

namespace App\Models;

use App\Enums\ProfileVisibility;
use App\Services\BlockService;
use App\Services\FollowService;
use App\Services\FollowSuggestionService;
use App\Services\PetFollowService;
use App\Services\ProfileVisibilityService;
use App\Support\Usernames\UsernameNormalizer;
use App\Support\Usernames\UsernameRules;
use App\Traits\HasCounterCache;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
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
    use SoftDeletes;

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

    protected static ?bool $hasBlocksTableCache = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'display_name',
        'username',
        'username_changed_at',
        'email',
        'password',
        'password_changed_at',
        'bio',
        'bio_html',
        'headline',
        'pronouns',
        'location',
        'website',
        'birth_date',
        'gender',
        'city',
        'country_code',
        'locale',
        'timezone',
        'profile_theme',
        'social_links',
        'interests_text',
        'profile_visibility',
        'messaging_permission',
        'pets_visibility',
        'groups_visibility',
        'show_in_explore',
        'open_following',
        'notification_preferences',
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
        'is_banned',
        'ban_reason',
        'role',
        'scheduled_deletion_at',
        'deletion_reason',
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
            'username_changed_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'password' => 'hashed',
            'birth_date' => 'date',
            'profile_visibility' => 'string',
            'messaging_permission' => 'string',
            'pets_visibility' => 'string',
            'groups_visibility' => 'string',
            'show_in_explore' => 'boolean',
            'open_following' => 'boolean',
            'notification_preferences' => 'array',
            'social_links' => 'array',
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
            'is_banned' => 'boolean',
            'scheduled_deletion_at' => 'datetime',
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
        return UsernameNormalizer::normalize($username);
    }

    public static function generateUniqueUsername(string $seed): string
    {
        $base = UsernameNormalizer::generateBase($seed);

        if ($base === '') {
            $base = 'petlover';
        }

        if (UsernameRules::disallowNumericOnly() && preg_match('/^[0-9]+$/', $base)) {
            $base = 'user_'.$base;
        }

        $maxLength = UsernameRules::maxLength();
        $base = Str::limit($base, $maxLength, '');
        $username = $base;
        $suffix = 0;

        if (! static::hasUsersColumn('username')) {
            return $username;
        }

        while (! UsernameRules::isAvailable($username)) {
            $suffix++;
            $suffixText = (string) $suffix;
            $availableLength = $maxLength - strlen($suffixText) - 1;
            $username = Str::limit($base, $availableLength, '').'_'.$suffixText;
        }

        return $username;
    }

    public static function isUsernameAvailable(string $username, ?self $ignore = null): bool
    {
        if (! static::hasUsersColumn('username')) {
            return false;
        }

        return UsernameRules::isAvailable($username, $ignore?->getKey());
    }

    public function setUsernameAttribute(string $value): void
    {
        $this->attributes['username'] = UsernameNormalizer::normalize($value);
    }

    public function canChangeUsername(): bool
    {
        if (! $this->username_changed_at) {
            return true;
        }

        $cooldownDays = (int) config('usernames.cooldown_days', 30);

        return $this->username_changed_at->copy()->addDays($cooldownDays)->isPast();
    }

    public function daysUntilUsernameChange(): int
    {
        if ($this->canChangeUsername()) {
            return 0;
        }

        $cooldownDays = (int) config('usernames.cooldown_days', 30);

        return (int) now()->diffInDays($this->username_changed_at->copy()->addDays($cooldownDays), false);
    }

    public function profileVisibility(): ProfileVisibility
    {
        return app(ProfileVisibilityService::class)->resolve($this);
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

    public function photoGalleries(): HasMany
    {
        return $this->hasMany(PhotoGallery::class);
    }

    public function followedPets(): BelongsToMany
    {
        return $this->belongsToMany(Pet::class, 'pet_followers', 'user_id', 'pet_id')
            ->withTimestamps();
    }

    public function petFollowing(): BelongsToMany
    {
        return $this->followedPets();
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function savedPosts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'saved_posts', 'user_id', 'post_id')
            ->withTimestamps();
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
        return $this->hasMany(Group::class, 'owner_id');
    }

    public function groupMemberships(): HasMany
    {
        return $this->hasMany(GroupMember::class);
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'group_members', 'user_id', 'group_id')
            ->withPivot(['role', 'joined_at', 'status', 'invited_by'])
            ->withTimestamps();
    }

    public function groupJoinRequests(): HasMany
    {
        return $this->hasMany(GroupJoinRequest::class);
    }

    public function groupBans(): HasMany
    {
        return $this->hasMany(GroupBan::class);
    }

    public function createdEvents(): HasMany
    {
        return $this->hasMany(Event::class, 'creator_user_id');
    }

    public function loadFeedContext(): self
    {
        return $this->load([
            'acceptedFollowing:id',
            'sentPendingRequests:id',
        ])->loadCount([
            'posts',
            'acceptedFollowers as followers_count',
            'acceptedFollowing as following_count',
        ]);
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

    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }

    public function conversationsAsOne(): HasMany
    {
        return $this->hasMany(Conversation::class, 'user_one_id');
    }

    public function conversationsAsTwo(): HasMany
    {
        return $this->hasMany(Conversation::class, 'user_two_id');
    }

    public function conversations(): Builder
    {
        return Conversation::query()
            ->forUser($this)
            ->ordered();
    }

    public function conversationWith(self $other): ?Conversation
    {
        return Conversation::query()
            ->where(function (Builder $query) use ($other): void {
                $query
                    ->where('user_one_id', $this->getKey())
                    ->where('user_two_id', $other->getKey());
            })
            ->orWhere(function (Builder $query) use ($other): void {
                $query
                    ->where('user_one_id', $other->getKey())
                    ->where('user_two_id', $this->getKey());
            })
            ->first();
    }

    public function totalUnreadMessages(): int
    {
        $userId = (int) $this->getKey();

        $asUserOne = (int) Conversation::query()
            ->where('user_one_id', $userId)
            ->sum('user_one_unread_count');

        $asUserTwo = (int) Conversation::query()
            ->where('user_two_id', $userId)
            ->sum('user_two_unread_count');

        return $asUserOne + $asUserTwo;
    }

    public function usernameRedirects(): HasMany
    {
        return $this->hasMany(UsernameRedirect::class);
    }

    public function usernameChanges(): HasMany
    {
        return $this->hasMany(UsernameChange::class);
    }

    public function sentMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function receivedMessages(): Builder
    {
        $userId = (int) $this->getKey();

        return Message::query()
            ->whereHas('conversation', function (Builder $query) use ($userId): void {
                $query->where(function (Builder $participantQuery) use ($userId): void {
                    $participantQuery
                        ->where('user_one_id', $userId)
                        ->orWhere('user_two_id', $userId);
                });
            })
            ->where('sender_id', '!=', $userId);
    }

    public function unreadMessagesCount(): int
    {
        return $this->totalUnreadMessages();
    }

    public function unreadThreadsCount(): int
    {
        $userId = (int) $this->getKey();

        return (int) Conversation::query()
            ->where(function (Builder $query) use ($userId): void {
                $query
                    ->where(function (Builder $asUserOne) use ($userId): void {
                        $asUserOne
                            ->where('user_one_id', $userId)
                            ->where('user_one_unread_count', '>', 0);
                    })
                    ->orWhere(function (Builder $asUserTwo) use ($userId): void {
                        $asUserTwo
                            ->where('user_two_id', $userId)
                            ->where('user_two_unread_count', '>', 0);
                    });
            })
            ->count();
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
            ->withPivot(['status', 'created_at']);
    }

    public function following(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'follows', 'follower_id', 'following_id')
            ->withPivot(['status', 'created_at']);
    }

    public function followings(): BelongsToMany
    {
        return $this->following();
    }

    public function acceptedFollowers(): BelongsToMany
    {
        return $this->followers()->wherePivot('status', 'accepted');
    }

    public function acceptedFollowing(): BelongsToMany
    {
        return $this->following()->wherePivot('status', 'accepted');
    }

    public function acceptedFollowings(): BelongsToMany
    {
        return $this->acceptedFollowing();
    }

    public function pendingFollowRequests(): BelongsToMany
    {
        return $this->followers()->wherePivot('status', 'pending');
    }

    public function sentPendingRequests(): BelongsToMany
    {
        return $this->following()->wherePivot('status', 'pending');
    }

    public function sentPendingFollowings(): BelongsToMany
    {
        return $this->sentPendingRequests();
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
        return $this->belongsToMany(Badge::class, 'user_badges')
            ->withPivot(['awarded_at', 'awarded_by', 'note'])
            ->orderByPivot('awarded_at', 'desc');
    }

    public function hasBadge(string $slug): bool
    {
        return $this->badges()->where('slug', $slug)->exists();
    }

    /**
     * @param  string|list<string>  $roles
     */
    public function hasAppRole(string|array $roles): bool
    {
        return in_array($this->role ?? 'member', (array) $roles, true);
    }

    public function contestEntries(): HasMany
    {
        return $this->hasMany(ContestEntry::class);
    }

    public function notificationEnabled(string $type): bool
    {
        $prefs = $this->notification_preferences;

        if (! is_array($prefs) || ! array_key_exists($type, $prefs)) {
            return true;
        }

        return (bool) $prefs[$type];
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

    public function scopeSearchResultColumns(Builder $query): Builder
    {
        return $query->select([
            'users.id',
            'users.name',
            'users.display_name',
            'users.username',
            'users.email',
            'users.city',
            'users.created_at',
            'users.profile_visibility',
            'users.is_private',
            'users.is_banned',
        ]);
    }

    public static function paginateSearchResults(?self $viewer, string $term, int $perPage = 15): LengthAwarePaginator
    {
        return self::query()
            ->searchResultColumns()
            ->discoverable()
            ->notBlockedFor($viewer)
            ->search($term !== '' ? $term : null)
            ->latest('users.created_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function scopeDiscoverable(Builder $query): Builder
    {
        return $query
            ->where(function (Builder $visibilityQuery): void {
                $visibilityQuery
                    ->whereNull('users.profile_visibility')
                    ->orWhere('users.profile_visibility', ProfileVisibility::Public->value);
            })
            ->where(function (Builder $legacyPrivacy): void {
                $legacyPrivacy
                    ->whereNull('users.is_private')
                    ->orWhere('users.is_private', false);
            })
            ->where('is_banned', false);
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query
            ->where(function (Builder $visibilityQuery): void {
                $visibilityQuery
                    ->whereNull('users.profile_visibility')
                    ->orWhere('users.profile_visibility', ProfileVisibility::Public->value);
            })
            ->where(function (Builder $legacyPrivacy): void {
                $legacyPrivacy
                    ->whereNull('users.is_private')
                    ->orWhere('users.is_private', false);
            })
            ->where('is_banned', false);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->select(['users.*'])
            ->where('users.is_banned', false)
            ->whereNull('users.scheduled_deletion_at');
    }

    public function scopeWithPublicProfile(Builder $query): Builder
    {
        return $query
            ->select(['users.*'])
            ->where(function (Builder $visibilityQuery): void {
                $visibilityQuery
                    ->whereNull('users.profile_visibility')
                    ->orWhere('users.profile_visibility', ProfileVisibility::Public->value);
            })
            ->where(function (Builder $privacyQuery): void {
                $privacyQuery
                    ->whereNull('users.is_private')
                    ->orWhere('users.is_private', false);
            })
            ->where('users.is_banned', false);
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

        if (! static::hasBlocksTable()) {
            return $query;
        }

        $query = $query
            ->whereNotIn(
                'users.id',
                $viewer->blocking()->select('users.id')
            )
            ->whereNotIn(
                'users.id',
                $viewer->blockedBy()->select('users.id')
            );

        return $query;
    }

    public function scopeVisibleTo(Builder $query, ?self $viewer): Builder
    {
        $query->select(['users.*'])->where('users.is_banned', false);

        if (! $viewer) {
            return $query
                ->discoverable()
                ->notBlockedFor($viewer);
        }

        $viewerId = (int) $viewer->getKey();

        return $query
            ->where(function (Builder $visibilityQuery) use ($viewerId, $viewer): void {
                $visibilityQuery
                    ->whereKey($viewerId)
                    ->orWhere(function (Builder $public): void {
                        $public
                            ->where(function (Builder $visibility): void {
                                $visibility
                                    ->whereNull('users.profile_visibility')
                                    ->orWhere('users.profile_visibility', ProfileVisibility::Public->value);
                            })
                            ->where(function (Builder $legacyPrivacy): void {
                                $legacyPrivacy
                                    ->whereNull('users.is_private')
                                    ->orWhere('users.is_private', false);
                            });
                    })
                    ->orWhere(function (Builder $followers) use ($viewer): void {
                        $followers
                            ->where(function (Builder $visibility): void {
                                $visibility
                                    ->where('users.profile_visibility', ProfileVisibility::FollowersOnly->value)
                                    ->orWhere(function (Builder $legacy): void {
                                        $legacy
                                            ->whereNull('users.profile_visibility')
                                            ->where('users.is_private', true);
                                    });
                            })
                            ->whereIn('users.id', $viewer->acceptedFollowing()->select('users.id'));
                    });
            })
            ->notBlockedFor($viewer);
    }

    public function scopeFollowedBy(Builder $query, self|int $user): Builder
    {
        $userId = $user instanceof self ? (int) $user->getKey() : (int) $user;

        return $query
            ->select(['users.*'])
            ->whereIn('users.id', Follow::query()
                ->select('following_id')
                ->where('follower_id', $userId)
                ->where('status', 'accepted'));
    }

    public function scopeNotBlocked(Builder $query, self|int|null $viewer): Builder
    {
        if ($viewer === null) {
            return $query->select(['users.*']);
        }

        if (! static::hasBlocksTable()) {
            return $query->select(['users.*']);
        }

        $viewerId = $viewer instanceof self ? (int) $viewer->getKey() : (int) $viewer;

        return $query
            ->select(['users.*'])
            ->whereNotIn('users.id', Block::query()
                ->select('blocked_id')
                ->where('blocker_id', $viewerId))
            ->whereNotIn('users.id', Block::query()
                ->select('blocker_id')
                ->where('blocked_id', $viewerId));
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

    public function canBeFollowedBy(?self $viewer): bool
    {
        if (! $viewer) {
            return false;
        }

        if ($viewer->is($this)) {
            return false;
        }

        if ((bool) $this->is_banned || (bool) $viewer->is_banned) {
            return false;
        }

        if ($this->profileVisibility() === ProfileVisibility::Private) {
            return false;
        }

        return ! $viewer->hasBlockingRelationshipWith($this);
    }

    public function followModeFor(self $viewer): string
    {
        return $this->profileVisibility() === ProfileVisibility::Public ? 'accepted' : 'pending';
    }

    public function canApproveFollowRequestFrom(self $requester): bool
    {
        if ($this->is($requester)) {
            return false;
        }

        if ((bool) $this->is_banned) {
            return false;
        }

        return ! $this->hasBlockingRelationshipWith($requester);
    }

    public function canRemoveFollower(self $follower): bool
    {
        if ($this->is($follower)) {
            return false;
        }

        return ! $this->hasBlockingRelationshipWith($follower);
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

    public function makePrivate(): void
    {
        DB::transaction(function (): void {
            app(ProfileVisibilityService::class)->syncLegacyPrivacy($this, ProfileVisibility::FollowersOnly);
        });
    }

    public function makePublic(): void
    {
        DB::transaction(function (): void {
            $pending = Follow::query()
                ->where('following_id', $this->getKey())
                ->where('status', 'pending')
                ->get();

            if ($pending->isNotEmpty()) {
                $requesterIds = $pending->pluck('follower_id')->unique()->values();

                Follow::query()
                    ->where('following_id', $this->getKey())
                    ->where('status', 'pending')
                    ->update(['status' => 'accepted']);

                $this->increment('followers_count', $pending->count());
                $this->updateQuietly(['follow_requests_count' => 0]);
                $notificationApprover = $this->withoutRelation([
                    'followers',
                    'following',
                    'acceptedFollowers',
                    'acceptedFollowing',
                ]);

                self::query()->whereIn('id', $requesterIds)->get()->each(function (self $requester) use ($notificationApprover): void {
                    $requester->increment('following_count');
                    $requester->notify(new \App\Notifications\FollowRequestApproved($notificationApprover));
                });
            }

            app(ProfileVisibilityService::class)->syncLegacyPrivacy($this, ProfileVisibility::Public);
        });
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
        return app(FollowSuggestionService::class)->forUser($this, $limit);
    }

    public function hasBlocked(self $user): bool
    {
        if (! static::hasBlocksTable()) {
            return false;
        }

        return $this->blocking()->whereKey($user->getKey())->exists();
    }

    public function isBlockedBy(self $user): bool
    {
        if (! static::hasBlocksTable()) {
            return false;
        }

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
        if (! static::hasBlocksTable()) {
            return $query;
        }

        $query = $query->whereNotIn('users.id', $user->blocking()->select('users.id'));

        return $query;
    }

    public function scopeNotBlocking(Builder $query, self $user): Builder
    {
        if (! static::hasBlocksTable()) {
            return $query;
        }

        return $query->whereNotIn('users.id', $user->blockedBy()->select('users.id'));
    }

    public function scopeHasNoBlockRelationshipWith(Builder $query, self $user): Builder
    {
        if (! static::hasBlocksTable()) {
            return $query;
        }

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
        return app(PetFollowService::class)->follow($this, $pet);
    }

    public function unfollowPet(Pet $pet): bool
    {
        return app(PetFollowService::class)->unfollow($this, $pet);
    }

    public function isFollowingPet(Pet $pet): bool
    {
        return $this->followedPets()->whereKey($pet->getKey())->exists();
    }

    public function petFollowingIds(): Collection
    {
        return $this->petFollowing()->pluck('pets.id');
    }

    public function canBeViewedBy(?self $viewer): bool
    {
        return $this->canViewProfile($viewer);
    }

    public function canViewFollowersList(?self $viewer): bool
    {
        return app(ProfileVisibilityService::class)->canViewFollowers($viewer, $this);
    }

    public function canViewFollowingList(?self $viewer): bool
    {
        return app(ProfileVisibilityService::class)->canViewFollowing($viewer, $this);
    }

    public function canView(Model $model): bool
    {
        if (! $model instanceof self) {
            return true;
        }

        return $model->canBeViewedBy($this);
    }

    public function canViewProfile(?self $viewer): bool
    {
        return app(ProfileVisibilityService::class)->canViewFullProfile($viewer, $this);
    }

    public function canViewPosts(?self $viewer): bool
    {
        return app(ProfileVisibilityService::class)->canViewProfilePosts($viewer, $this);
    }

    public function canSeeFollowersList(?self $viewer): bool
    {
        return $this->canViewFollowersList($viewer);
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

    public function coverImageUrl(): ?string
    {
        $mediaUrl = $this->getFirstMediaUrl(self::MEDIA_COLLECTION_COVER, self::MEDIA_CONVERSION_COVER_BANNER);

        if ($mediaUrl !== '') {
            return $mediaUrl;
        }

        if (! empty($this->cover_photo_path)) {
            return (string) $this->cover_photo_path;
        }

        return null;
    }

    public function getAvatarUrl(): string
    {
        return (string) ($this->avatar_url ?: '/images/default-avatar.png');
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

    public static function hasBlocksTable(): bool
    {
        if (static::$hasBlocksTableCache === null) {
            static::$hasBlocksTableCache = Schema::hasTable('blocks');
        }

        return static::$hasBlocksTableCache;
    }

    protected function avatarUrl(): Attribute
    {
        return Attribute::get(function (): ?string {
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

            return null;
        });
    }

    protected function coverPhotoUrl(): Attribute
    {
        return Attribute::get(function (): ?string {
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
        return Attribute::get(fn (): ?string => $this->firstMediaUrl(self::MEDIA_COLLECTION_PROFILE) ?: $this->avatar_url);
    }

    protected function profileUrl(): Attribute
    {
        return Attribute::get(fn (): string => route('profile.show', ['user' => $this->username]));
    }

    protected function atUsername(): Attribute
    {
        return Attribute::get(fn (): string => '@'.$this->username);
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
