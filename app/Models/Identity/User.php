<?php

namespace App\Models\Identity;

use App\Enums\AccountStatus;
use App\Enums\ProfileTheme;
use App\Enums\ProfileVisibility;
use App\Models\Activities\ContestEntry;
use App\Models\Activities\Event;
use App\Models\Activities\EventAttendee;
use App\Models\Analytics\ProfileView;
use App\Models\Analytics\ProfileWrappedSummary;
use App\Models\Content\Comment;
use App\Models\Content\Post;
use App\Models\Content\Reaction;
use App\Models\Content\Share;
use App\Models\Gamification\Badge;
use App\Models\Groups\Group;
use App\Models\Groups\GroupBan;
use App\Models\Groups\GroupJoinRequest;
use App\Models\Groups\GroupMember;
use App\Models\Marketplace\Listing;
use App\Models\Marketplace\MarketplaceListing;
use App\Models\Messaging\Conversation;
use App\Models\Messaging\Message;
use App\Models\Moderation\Report;
use App\Models\Pets\Pet;
use App\Models\Pets\PetHealthLog;
use App\Models\Pets\PetOwner;
use App\Models\Pets\PhotoGallery;
use App\Models\Social\Block;
use App\Models\Social\Follow;
use App\Notifications\FollowRequestApproved;
use App\Services\BlockService;
use App\Services\FollowService;
use App\Services\FollowSuggestionService;
use App\Services\PetFollowService;
use App\Services\ProfileVisibilityService;
use App\Support\Usernames\UsernameNormalizer;
use App\Support\Usernames\UsernameRules;
use App\Traits\HasCounterCache;
use Carbon\CarbonInterface;
use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Query\JoinClause;
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

#[UseFactory(UserFactory::class)]
#[Appends([
    'avatar_url',
    'cover_photo_url',
    'profile_photo_url',
    'profile_initial',
    'profile_default_gradient',
    'profile_default_avatar_color',
    'profile_verified',
    'profile_completeness_percentage',
    'profile_completeness_missing_items',
    'age_formatted',
])]
#[Fillable([
    'name',
    'display_name',
    'username',
    'username_change_allowed_at',
    'username_changed_at',
    'email',
    'pending_email',
    'password',
    'password_changed_at',
    'two_factor_secret',
    'two_factor_recovery_codes',
    'terms_accepted_at',
    'terms_version',
    'registration_ip_address',
    'registration_user_agent',
    'bio',
    'bio_html',
    'headline',
    'pronouns',
    'location',
    'location_lat',
    'location_lng',
    'website',
    'birth_date',
    'gender',
    'city',
    'country_code',
    'locale',
    'timezone',
    'social_links',
    'interests_text',
    'privacy_display_email',
    'privacy_display_location',
    'privacy_display_birthdate',
    'privacy_display_last_seen',
    'profile_visibility',
    'profile_theme',
    'messaging_permission',
    'pets_visibility',
    'groups_visibility',
    'show_in_explore',
    'open_following',
    'notification_preferences',
    'is_private',
    'onboarding_step',
    'onboarding_completed_at',
    'last_active_at',
    'last_seen_at',
    'last_login_at',
    'avatar_path',
    'cover_photo_path',
    'cover_photo_position',
    'profile_photo_path',
    'is_verified',
    'profile_completed_at',
    'profile_completeness_score',
    'followers_count',
    'following_count',
    'follow_requests_count',
    'following_pets_count',
    'pets_count',
    'posts_count',
    'photos_count',
    'scheduled_posts_count',
    'post_reactions_received_count',
    'post_comments_received_count',
    'last_post_created_at',
    'blocked_users_count',
    'blocked_by_count',
    'is_banned',
    'account_status',
    'ban_reason',
    'role',
    'scheduled_deletion_at',
    'deletion_reason',
    'deactivated_at',
    'deactivation_reason',
    'suspended_until',
    'suspension_reason',
    'failed_login_attempts',
    'last_failed_login_at',
])]
#[Hidden([
    'password',
    'remember_token',
    'pending_email',
    'two_factor_secret',
    'two_factor_recovery_codes',
])]
class User extends Authenticatable implements HasMedia, MustVerifyEmail
{
    use HasCounterCache;
    use HasFactory;
    use HasRoles;
    use InteractsWithMedia;
    use MustVerifyEmailTrait;
    use Notifiable;
    use SoftDeletes;

    public const MEDIA_COLLECTION_AVATAR = 'avatar';

    public const MEDIA_COLLECTION_COVER = 'cover';

    public const MEDIA_COLLECTION_PROFILE = 'profile';

    public const MEDIA_COLLECTION_PHOTOS = 'photos';

    public const MEDIA_CONVERSION_AVATAR_THUMB = 'avatar_thumb';

    public const MEDIA_CONVERSION_AVATAR_CARD = 'avatar_card';

    public const MEDIA_CONVERSION_COVER_BANNER = 'cover_banner';

    public const DEFAULT_COVER_PHOTO_POSITION = 50.0;

    public const MIN_COVER_PHOTO_POSITION = 0.0;

    public const MAX_COVER_PHOTO_POSITION = 100.0;

    public const CURRENT_TERMS_VERSION = '2026-05-18';

    public const ACTIVE_STATUS_WINDOW_MINUTES = 5;

    public const ACTIVE_STATUS_WRITE_THROTTLE_SECONDS = 60;

    private const PROFILE_DEFAULT_GRADIENTS = [
        'bg-gradient-to-r from-paw-light via-cream to-sky-light',
        'bg-gradient-to-r from-amber-100 via-cream to-paw-light',
        'bg-gradient-to-r from-emerald-100 via-cream to-sky-light',
        'bg-gradient-to-r from-rose-light via-cream to-amber-100',
        'bg-gradient-to-r from-sky-light via-cream to-emerald-100',
    ];

    private const PROFILE_DEFAULT_AVATAR_COLORS = [
        'bg-paw-light text-paw-dark',
        'bg-amber-100 text-amber',
        'bg-emerald-100 text-leaf',
        'bg-rose-light text-rose',
        'bg-sky-light text-sky',
    ];

    /**
     * @var array<string, bool>
     */
    protected static array $usersColumnsCache = [];

    protected static ?bool $hasBlocksTableCache = null;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'pending_email' => 'string',
            'username_change_allowed_at' => 'datetime',
            'username_changed_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'terms_accepted_at' => 'datetime',
            'password' => 'hashed',
            'birth_date' => 'date',
            'location_lat' => 'float',
            'location_lng' => 'float',
            'profile_visibility' => 'string',
            'profile_theme' => ProfileTheme::class,
            'messaging_permission' => 'string',
            'pets_visibility' => 'string',
            'groups_visibility' => 'string',
            'privacy_display_email' => 'boolean',
            'privacy_display_location' => 'boolean',
            'privacy_display_birthdate' => 'boolean',
            'privacy_display_last_seen' => 'boolean',
            'show_in_explore' => 'boolean',
            'open_following' => 'boolean',
            'notification_preferences' => 'array',
            'social_links' => 'array',
            'is_private' => 'boolean',
            'onboarding_completed_at' => 'datetime',
            'last_active_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'last_login_at' => 'datetime',
            'cover_photo_position' => 'float',
            'is_verified' => 'boolean',
            'profile_completed_at' => 'datetime',
            'profile_completeness_score' => 'integer',
            'followers_count' => 'integer',
            'following_count' => 'integer',
            'follow_requests_count' => 'integer',
            'following_pets_count' => 'integer',
            'pets_count' => 'integer',
            'posts_count' => 'integer',
            'photos_count' => 'integer',
            'scheduled_posts_count' => 'integer',
            'post_reactions_received_count' => 'integer',
            'post_comments_received_count' => 'integer',
            'last_post_created_at' => 'datetime',
            'blocked_users_count' => 'integer',
            'blocked_by_count' => 'integer',
            'is_banned' => 'boolean',
            'account_status' => AccountStatus::class,
            'scheduled_deletion_at' => 'datetime',
            'deactivated_at' => 'datetime',
            'suspended_until' => 'datetime',
            'failed_login_attempts' => 'integer',
            'last_failed_login_at' => 'datetime',
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

        if (UsernameRules::disallowNumericOnly() && preg_match('/^\d+$/', $base)) {
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

    public function profileTheme(): ProfileTheme
    {
        $theme = $this->profile_theme;

        if ($theme instanceof ProfileTheme) {
            return $theme;
        }

        return ProfileTheme::fromValue(is_string($theme) ? $theme : null) ?? ProfileTheme::default();
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultRegistrationPrivacySettings(): array
    {
        return [
            'is_private' => false,
            'privacy_display_email' => false,
            'privacy_display_location' => false,
            'privacy_display_birthdate' => false,
            'privacy_display_last_seen' => true,
            'profile_visibility' => ProfileVisibility::Public->value,
            'messaging_permission' => 'followers_only',
            'pets_visibility' => 'followers_only',
            'groups_visibility' => 'followers_only',
            'show_in_explore' => true,
            'open_following' => false,
        ];
    }

    /**
     * @return array<string, bool>
     */
    public static function defaultNotificationPreferences(): array
    {
        return [
            'post_likes' => true,
            'post_comments' => true,
            'comment_replies' => true,
            'mentions' => true,
            'follow_requests' => true,
            'new_follower' => true,
            'direct_messages' => true,
            'group_invites' => true,
            'group_updates' => true,
            'event_invites' => true,
            'event_reminders' => true,
            'marketplace_messages' => true,
            'contest_updates' => true,
            'system_announcements' => true,
            'security_alerts' => true,
            'verification_emails' => true,
            'password_resets' => true,
            'marketing' => false,
        ];
    }

    public function setUsernameAttribute(string $value): void
    {
        $this->attributes['username'] = UsernameNormalizer::normalize($value);
    }

    public function canChangeUsername(): bool
    {
        if (! $this->username_change_allowed_at) {
            return true;
        }

        $cooldownDays = (int) config('usernames.cooldown_days', 30);

        return $this->username_change_allowed_at->copy()->addDays($cooldownDays)->isPast();
    }

    public function daysUntilUsernameChange(): int
    {
        if ($this->canChangeUsername()) {
            return 0;
        }

        $cooldownDays = (int) config('usernames.cooldown_days', 30);

        return (int) now()->diffInDays($this->username_change_allowed_at->copy()->addDays($cooldownDays), false);
    }

    public function hasPendingDeletion(): bool
    {
        return $this->scheduled_deletion_at !== null;
    }

    public function isDeactivated(): bool
    {
        return $this->deactivated_at !== null;
    }

    public function isSuspended(): bool
    {
        $suspendedUntil = $this->getAttribute('suspended_until');

        return $suspendedUntil instanceof CarbonInterface && $suspendedUntil->isFuture();
    }

    public function isUnavailableForProfile(): bool
    {
        return $this->trashed()
            || (bool) $this->is_banned
            || $this->hasPendingDeletion()
            || $this->isDeactivated()
            || $this->isSuspended();
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
            ->performOnCollections(self::MEDIA_COLLECTION_AVATAR);

        $this->addMediaConversion(self::MEDIA_CONVERSION_AVATAR_CARD)
            ->fit(Fit::Crop, 256, 256)
            ->performOnCollections(self::MEDIA_COLLECTION_AVATAR);

        $this->addMediaConversion(self::MEDIA_CONVERSION_COVER_BANNER)
            ->fit(Fit::Crop, 1600, 480)
            ->performOnCollections(self::MEDIA_COLLECTION_COVER);
    }

    public function pets(): HasMany
    {
        return $this->hasMany(Pet::class);
    }

    /**
     * @return HasMany<PetOwner, $this>
     */
    public function petOwnerships(): HasMany
    {
        return $this->hasMany(PetOwner::class);
    }

    /**
     * @return BelongsToMany<Pet, $this>
     */
    public function coOwnedPets(): BelongsToMany
    {
        return $this->belongsToMany(Pet::class, 'pet_owners', 'user_id', 'pet_id')
            ->withPivot([
                'role',
                'can_post',
                'can_edit',
                'can_manage_health',
                'can_manage_gallery',
                'can_manage_adoption',
                'can_delete',
                'accepted_at',
            ])
            ->withTimestamps();
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

    /**
     * @return HasMany<ProfilePortfolioPost, $this>
     */
    public function profilePortfolioPosts(): HasMany
    {
        return $this->hasMany(ProfilePortfolioPost::class);
    }

    /**
     * @return BelongsToMany<Post, $this>
     */
    public function portfolioPosts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'profile_portfolio_posts')
            ->withPivot(['display_order'])
            ->orderBy('profile_portfolio_posts.display_order')
            ->withTimestamps();
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

    public function shares(): HasMany
    {
        return $this->hasMany(Share::class);
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
            ->using(GroupMember::class)
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

    /**
     * @return HasMany<SocialAccount, $this>
     */
    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
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
            ->where('reportable_type', $this->getMorphClass());
    }

    public function reports(): MorphMany
    {
        return $this->morphMany(Report::class, 'reportable');
    }

    public function resolvedReports(): HasMany
    {
        return $this->hasMany(Report::class, 'reviewed_by_user_id');
    }

    public function petHealthLogs(): HasMany
    {
        return $this->hasMany(PetHealthLog::class, 'logged_by_user_id');
    }

    /**
     * @return HasMany<ProfileView, $this>
     */
    public function profileViews(): HasMany
    {
        return $this->hasMany(ProfileView::class, 'profile_user_id');
    }

    /**
     * @return HasMany<ProfileWrappedSummary, $this>
     */
    public function profileWrappedSummaries(): HasMany
    {
        return $this->hasMany(ProfileWrappedSummary::class);
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
                ->orWhere('display_name', 'like', "%{$term}%")
                ->orWhere('username', 'like', "%{$term}%")
                ->orWhere('headline', 'like', "%{$term}%");
        });
    }

    public function scopeSearchResultColumns(Builder $query): Builder
    {
        return $query->select([
            'users.id',
            'users.name',
            'users.display_name',
            'users.username',
            'users.headline',
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
        return self::applyAvailableForProfiles($query)
            ->where(function (Builder $exploreQuery): void {
                $exploreQuery
                    ->whereNull('users.show_in_explore')
                    ->orWhere('users.show_in_explore', true);
            })
            ->where(function (Builder $visibilityQuery): void {
                $visibilityQuery
                    ->whereNull('users.profile_visibility')
                    ->orWhere('users.profile_visibility', ProfileVisibility::Public->value);
            })
            ->where(function (Builder $legacyPrivacy): void {
                $legacyPrivacy
                    ->whereNull('users.is_private')
                    ->orWhere('users.is_private', false);
            });
    }

    public function scopePublic(Builder $query): Builder
    {
        return self::applyAvailableForProfiles($query)
            ->where(function (Builder $visibilityQuery): void {
                $visibilityQuery
                    ->whereNull('users.profile_visibility')
                    ->orWhere('users.profile_visibility', ProfileVisibility::Public->value);
            })
            ->where(function (Builder $legacyPrivacy): void {
                $legacyPrivacy
                    ->whereNull('users.is_private')
                    ->orWhere('users.is_private', false);
            });
    }

    public function scopeActive(Builder $query): Builder
    {
        return self::applyAvailableForProfiles($query->select(['users.*']));
    }

    public function scopeWithPublicProfile(Builder $query): Builder
    {
        return self::applyAvailableForProfiles($query->select(['users.*']))
            ->where(function (Builder $visibilityQuery): void {
                $visibilityQuery
                    ->whereNull('users.profile_visibility')
                    ->orWhere('users.profile_visibility', ProfileVisibility::Public->value);
            })
            ->where(function (Builder $privacyQuery): void {
                $privacyQuery
                    ->whereNull('users.is_private')
                    ->orWhere('users.is_private', false);
            });
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeAvailableForProfiles(Builder $query): Builder
    {
        return self::applyAvailableForProfiles($query);
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function applyAvailableForProfiles(Builder $query): Builder
    {
        $query->where('users.is_banned', false);

        if (static::hasUsersColumn('scheduled_deletion_at')) {
            $query->whereNull('users.scheduled_deletion_at');
        }

        if (static::hasUsersColumn('deactivated_at')) {
            $query->whereNull('users.deactivated_at');
        }

        if (static::hasUsersColumn('suspended_until')) {
            $query->where(function (Builder $suspensionQuery): void {
                $suspensionQuery
                    ->whereNull('users.suspended_until')
                    ->orWhere('users.suspended_until', '<=', now());
            });
        }

        return $query;
    }

    public function scopeActiveRecently(Builder $query, int $days = 30): Builder
    {
        return $query->where('last_active_at', '>=', now()->subDays($days));
    }

    public function isCurrentlyActive(?CarbonInterface $now = null): bool
    {
        if (! $this->last_active_at instanceof CarbonInterface) {
            return false;
        }

        $currentTime = $now ?? now();

        return $this->last_active_at->greaterThanOrEqualTo(
            $currentTime->copy()->subMinutes(self::ACTIVE_STATUS_WINDOW_MINUTES)
        );
    }

    public function shouldShowActiveStatus(?CarbonInterface $now = null): bool
    {
        return (bool) $this->privacy_display_last_seen && $this->isCurrentlyActive($now);
    }

    public function scopeNotBlockedFor(Builder $query, ?self $viewer): Builder
    {
        if (! $viewer instanceof User) {
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
        self::applyAvailableForProfiles($query->select(['users.*']));

        if ($viewer instanceof User && $viewer->isUnavailableForProfile()) {
            return $query->whereKey(-1);
        }

        if (! $viewer instanceof User) {
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
        $userId = $user instanceof self ? (int) $user->getKey() : $user;

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

        $viewerId = $viewer instanceof self ? (int) $viewer->getKey() : $viewer;

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
        if (! $viewer instanceof User) {
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
                    $requester->notify(new FollowRequestApproved($notificationApprover));
                });
            }

            app(ProfileVisibilityService::class)->syncLegacyPrivacy($this, ProfileVisibility::Public);
        });
    }

    /**
     * @return Collection<int, self>
     */
    public function getMutualFollowers(self $other, int $limit = 5): Collection
    {
        $viewerId = (int) $this->getKey();
        $profileUserId = (int) $other->getKey();

        if ($viewerId === 0 || $profileUserId === 0 || $viewerId === $profileUserId) {
            return collect();
        }

        $query = self::query()
            ->select([
                'users.id',
                'users.name',
                'users.username',
                'users.avatar_path',
                'users.profile_photo_path',
            ])
            ->join('follows as viewer_followers', function (JoinClause $join) use ($viewerId): void {
                $join
                    ->on('viewer_followers.follower_id', '=', 'users.id')
                    ->where('viewer_followers.following_id', $viewerId)
                    ->where('viewer_followers.status', 'accepted');
            })
            ->join('follows as profile_followers', function (JoinClause $join) use ($profileUserId): void {
                $join
                    ->on('profile_followers.follower_id', '=', 'users.id')
                    ->where('profile_followers.following_id', $profileUserId)
                    ->where('profile_followers.status', 'accepted');
            })
            ->notBlockedFor($this)
            ->limit(max(1, $limit))
            ->with('media');

        self::applyAvailableForProfiles($query);

        return $query->get();
    }

    public function getSuggestedUsersToFollow(int $limit = 6): Collection
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

    public function updateCover(UploadedFile $file, mixed $position = null): void
    {
        $this->storeProfileMedia($file, self::MEDIA_COLLECTION_COVER);

        $this->forceFill([
            'cover_photo_path' => null,
            'cover_photo_position' => $position === null
                ? self::DEFAULT_COVER_PHOTO_POSITION
                : self::normalizeCoverPhotoPosition($position),
        ])->saveQuietly();
    }

    public static function normalizeCoverPhotoPosition(mixed $position): float
    {
        $value = is_numeric($position) ? (float) $position : self::DEFAULT_COVER_PHOTO_POSITION;

        return round(min(self::MAX_COVER_PHOTO_POSITION, max(self::MIN_COVER_PHOTO_POSITION, $value)), 2);
    }

    public function coverPhotoPositionPercentage(): float
    {
        return self::normalizeCoverPhotoPosition($this->cover_photo_position);
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
        if ($key === 'cover_photo_position') {
            $value = self::normalizeCoverPhotoPosition($value);
        }

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

        if (! $media instanceof Media) {
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

    /**
     * @return Attribute<string, never>
     */
    protected function profileUrl(): Attribute
    {
        return Attribute::get(fn (): string => route('profile.show', ['user' => $this->username]));
    }

    /**
     * @return Attribute<uppercase-string, never>
     */
    protected function profileInitial(): Attribute
    {
        return Attribute::get(function (): string {
            $name = trim((string) $this->name);
            $displayName = trim((string) $this->display_name);
            $source = $name !== '' ? $name : ($displayName !== '' ? $displayName : (string) $this->username);

            return mb_strtoupper(mb_substr($source, 0, 1));
        });
    }

    /**
     * @return Attribute<string, never>
     */
    protected function profileDefaultGradient(): Attribute
    {
        return Attribute::get(fn (): string => self::PROFILE_DEFAULT_GRADIENTS[$this->profileDefaultPaletteIndex()]);
    }

    /**
     * @return Attribute<string, never>
     */
    protected function profileDefaultAvatarColor(): Attribute
    {
        return Attribute::get(fn (): string => self::PROFILE_DEFAULT_AVATAR_COLORS[$this->profileDefaultPaletteIndex()]);
    }

    private function profileDefaultPaletteIndex(): int
    {
        $seed = (string) ($this->username ?: $this->email ?: $this->getKey());

        return abs(crc32($seed)) % count(self::PROFILE_DEFAULT_GRADIENTS);
    }

    /**
     * @return Attribute<bool, never>
     */
    protected function profileVerified(): Attribute
    {
        return Attribute::get(fn (): bool => (bool) ($this->is_verified ?? false));
    }

    /**
     * @return Attribute<int, never>
     */
    protected function profileCompletenessPercentage(): Attribute
    {
        return Attribute::get(fn (): int => $this->profileCompletenessPercentageValue());
    }

    /**
     * @return Attribute<list<array{key: string, label: string}>, never>
     */
    protected function profileCompletenessMissingItems(): Attribute
    {
        return Attribute::get(fn (): array => $this->profileCompletenessMissingItemsValue());
    }

    protected function atUsername(): Attribute
    {
        return Attribute::get(fn (): string => '@'.$this->username);
    }

    protected function bioHtml(): Attribute
    {
        return Attribute::get(function (?string $value): ?string {
            if (filled($value)) {
                return $value;
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

    public function profileCompletenessPercentageValue(): int
    {
        return (int) collect($this->profileCompletenessItems())->sum('points');
    }

    /**
     * @return array{percentage: int, missing_items: list<array{key: string, label: string}>}
     */
    public static function profileCompletenessSummaryFor(int $userId): array
    {
        $user = self::query()
            ->select([
                'id',
                'bio',
                'location',
                'city',
                'website',
                'birth_date',
                'avatar_path',
                'profile_photo_path',
                'cover_photo_path',
            ])
            ->withCount([
                'pets as pets_count',
                'acceptedFollowing as following_count',
            ])
            ->withExists([
                'media as has_profile_avatar_media' => fn (Builder $query): Builder => $query
                    ->where('collection_name', self::MEDIA_COLLECTION_AVATAR),
                'media as has_profile_cover_media' => fn (Builder $query): Builder => $query
                    ->where('collection_name', self::MEDIA_COLLECTION_COVER),
            ])
            ->find($userId);

        if (! $user instanceof self) {
            return [
                'percentage' => 0,
                'missing_items' => [],
            ];
        }

        return $user->profileCompletenessSummaryValue();
    }

    /**
     * @return array{percentage: int, missing_items: list<array{key: string, label: string}>}
     */
    public function profileCompletenessSummaryValue(): array
    {
        return [
            'percentage' => $this->profile_completeness_percentage,
            'missing_items' => $this->profile_completeness_missing_items,
        ];
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    public function profileCompletenessMissingItemsValue(): array
    {
        return collect($this->profileCompletenessItems())
            ->reject(fn (array $item): bool => $item['complete'])
            ->map(fn (array $item): array => [
                'key' => $item['key'],
                'label' => $item['label'],
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{key: string, label: string, complete: bool, points: int}>
     */
    protected function profileCompletenessItems(): array
    {
        return [
            [
                'key' => 'avatar',
                'label' => 'Add a profile photo',
                'complete' => $this->hasProfileAvatar(),
                'points' => $this->hasProfileAvatar() ? 15 : 0,
            ],
            [
                'key' => 'cover',
                'label' => 'Add a cover photo',
                'complete' => $this->hasProfileCover(),
                'points' => $this->hasProfileCover() ? 15 : 0,
            ],
            [
                'key' => 'bio',
                'label' => 'Write a bio of at least 20 characters',
                'complete' => Str::length(trim(strip_tags((string) $this->bio))) >= 20,
                'points' => Str::length(trim(strip_tags((string) $this->bio))) >= 20 ? 15 : 0,
            ],
            [
                'key' => 'location',
                'label' => 'Add your location',
                'complete' => filled($this->location ?? $this->city),
                'points' => filled($this->location ?? $this->city) ? 10 : 0,
            ],
            [
                'key' => 'website',
                'label' => 'Add your website',
                'complete' => filled($this->website),
                'points' => filled($this->website) ? 10 : 0,
            ],
            [
                'key' => 'birth_date',
                'label' => 'Add your date of birth',
                'complete' => $this->birth_date !== null,
                'points' => $this->birth_date !== null ? 10 : 0,
            ],
            [
                'key' => 'pets',
                'label' => 'Create at least one pet profile',
                'complete' => $this->hasCompletedPetRequirement(),
                'points' => $this->hasCompletedPetRequirement() ? 15 : 0,
            ],
            [
                'key' => 'following',
                'label' => 'Follow at least 5 accounts',
                'complete' => (int) ($this->following_count ?? 0) >= 5,
                'points' => (int) ($this->following_count ?? 0) >= 5 ? 10 : 0,
            ],
        ];
    }

    protected function hasProfileAvatar(): bool
    {
        if (array_key_exists('has_profile_avatar_media', $this->attributes)) {
            return (bool) $this->attributes['has_profile_avatar_media']
                || filled($this->avatar_path)
                || filled($this->profile_photo_path);
        }

        return $this->firstMediaUrl(self::MEDIA_COLLECTION_AVATAR) !== ''
            || filled($this->avatar_path)
            || filled($this->profile_photo_path);
    }

    protected function hasProfileCover(): bool
    {
        if (array_key_exists('has_profile_cover_media', $this->attributes)) {
            return (bool) $this->attributes['has_profile_cover_media']
                || filled($this->cover_photo_path);
        }

        return $this->firstMediaUrl(self::MEDIA_COLLECTION_COVER) !== ''
            || filled($this->cover_photo_path);
    }

    protected function hasCompletedPetRequirement(): bool
    {
        if ((int) ($this->pets_count ?? 0) > 0) {
            return true;
        }

        if ($this->relationLoaded('pets')) {
            return $this->pets->isNotEmpty();
        }

        return $this->exists && $this->pets()->exists();
    }
}
