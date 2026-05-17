<?php

namespace App\Models\Groups;

use App\Enums\GroupMemberStatus;
use App\Models\Activities\Event;
use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Services\GroupSlugService;
use App\Traits\HasCounterCache;
use Database\Factories\GroupFactory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

#[UseFactory(GroupFactory::class)]
#[Appends([
    'avatar_url',
    'cover_url',
    'cover_photo_url',
    'profile_photo_url',
])]
#[Fillable([
    'owner_id',
    'owner_user_id',
    'name',
    'slug',
    'description',
    'description_html',
    'avatar',
    'avatar_path',
    'cover_image',
    'cover_image_path',
    'type',
    'privacy',
    'status',
    'archived_at',
    'species_focus',
    'species',
    'rules',
    'location',
    'website',
    'members_count',
    'posts_count',
])]
class Group extends Model implements HasMedia
{
    use HasCounterCache;
    use HasFactory;
    use InteractsWithMedia;
    use SoftDeletes;

    public const MEDIA_COLLECTION_AVATAR = 'avatar';

    public const MEDIA_COLLECTION_COVER = 'cover';

    public const MEDIA_CONVERSION_THUMB = 'thumb';

    public const MEDIA_CONVERSION_MEDIUM = 'medium';

    public const MEDIA_CONVERSION_LARGE = 'large';

    protected function casts(): array
    {
        return [
            'members_count' => 'integer',
            'posts_count' => 'integer',
            'archived_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $group): void {
            $group->syncOwnerColumns();

            if (blank($group->slug)) {
                $group->slug = app(GroupSlugService::class)->generateUnique(
                    (string) ($group->name ?: 'group'),
                    $group->exists ? (int) $group->getKey() : null,
                );
            }

            if ($group->isDirty('slug')) {
                $group->slug = app(GroupSlugService::class)->generateUnique(
                    (string) $group->slug,
                    $group->exists ? (int) $group->getKey() : null,
                );
            }

            if (blank($group->privacy)) {
                $group->privacy = 'public';
            }

            if (blank($group->type)) {
                $group->type = $group->privacy;
            }

            if (blank($group->species_focus)) {
                $group->species_focus = 'all';
            }

            if (blank($group->status)) {
                $group->status = 'active';
            }
        });
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
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion(self::MEDIA_CONVERSION_THUMB)
            ->fit(Fit::Crop, 150, 150)
            ->format('webp')
            ->quality(80)
            ->performOnCollections(self::MEDIA_COLLECTION_AVATAR, self::MEDIA_COLLECTION_COVER)
            ->nonQueued();

        $this->addMediaConversion(self::MEDIA_CONVERSION_MEDIUM)
            ->width(800)
            ->format('webp')
            ->quality(85)
            ->performOnCollections(self::MEDIA_COLLECTION_COVER)
            ->nonQueued();

        $this->addMediaConversion(self::MEDIA_CONVERSION_LARGE)
            ->width(1200)
            ->format('webp')
            ->quality(90)
            ->performOnCollections(self::MEDIA_COLLECTION_COVER)
            ->nonQueued();
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(GroupMember::class);
    }

    public function memberships(): HasMany
    {
        return $this->members();
    }

    public function joinRequests(): HasMany
    {
        return $this->hasMany(GroupJoinRequest::class);
    }

    public function bans(): HasMany
    {
        return $this->hasMany(GroupBan::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function sharedPosts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'group_posts', 'group_id', 'post_id')
            ->withPivot(['added_by_user_id'])
            ->withTimestamps();
    }

    public function attachSharedPost(Post $post, int $addedByUserId): void
    {
        if (! Schema::hasTable('group_posts')) {
            return;
        }

        $this->sharedPosts()->syncWithoutDetaching([
            $post->getKey() => [
                'added_by_user_id' => $addedByUserId,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function detachSharedPost(Post $post): void
    {
        if (! Schema::hasTable('group_posts')) {
            return;
        }

        $this->sharedPosts()->detach($post->getKey());
    }

    public function includesPost(Post $post): bool
    {
        if (Schema::hasColumn('posts', 'group_id') && (int) $post->group_id === (int) $this->getKey()) {
            return true;
        }

        if (! Schema::hasTable('group_posts')) {
            return false;
        }

        return $this->sharedPosts()
            ->where('posts.id', $post->getKey())
            ->exists();
    }

    public function calculatePostsCount(): int
    {
        $postIds = collect();

        if (Schema::hasColumn('posts', 'group_id')) {
            $postIds = $postIds->merge(
                $this->posts()->pluck('posts.id')
            );
        }

        if (Schema::hasTable('group_posts')) {
            $postIds = $postIds->merge(
                $this->sharedPosts()->pluck('posts.id')
            );
        }

        return $postIds->unique()->count();
    }

    public function syncPostsCount(): void
    {
        if (! Schema::hasColumn('groups', 'posts_count')) {
            return;
        }

        $this->forceFill([
            'posts_count' => $this->calculatePostsCount(),
        ])->save();
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function scopeVisible(Builder $query, ?User $user): Builder
    {
        return $query->where(function (Builder $visibilityQuery) use ($user): void {
            $visibilityQuery
                ->where(function (Builder $discoverableQuery): void {
                    $discoverableQuery
                        ->where(function (Builder $privacyQuery): void {
                            $privacyQuery
                                ->whereNull('privacy')
                                ->orWhere('privacy', '!=', 'secret');
                        })
                        ->where(function (Builder $typeQuery): void {
                            $typeQuery
                                ->whereNull('type')
                                ->orWhere('type', '!=', 'secret');
                        });
                });

            if ($user instanceof User) {
                $visibilityQuery
                    ->orWhere('owner_id', $user->getKey())
                    ->orWhere('owner_user_id', $user->getKey())
                    ->orWhereHas('members', function (Builder $memberQuery) use ($user): void {
                        $memberQuery
                            ->where('user_id', $user->getKey())
                            ->where(function (Builder $statusQuery): void {
                                if (Schema::hasColumn('group_members', 'status')) {
                                    $statusQuery
                                        ->whereNull('status')
                                        ->orWhereIn('status', ['active', 'accepted']);
                                }
                            });
                    });
            }
        });
    }

    public function scopeForSpecies(Builder $query, string $species): Builder
    {
        $value = strtolower(trim($species));

        if ($value === '' || $value === 'all') {
            return $query;
        }

        return $query->where(function (Builder $speciesQuery) use ($value): void {
            if (Schema::hasColumn('groups', 'species_focus')) {
                $speciesQuery->where('species_focus', $value);
            }

            if (Schema::hasColumn('groups', 'species')) {
                $speciesQuery->orWhere('species', $value);
            }
        });
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        return $query->where(function (Builder $subQuery) use ($term): void {
            $subQuery
                ->where('name', 'like', "%{$term}%")
                ->orWhere('slug', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%");
        });
    }

    public function scopeSearchResultColumns(Builder $query): Builder
    {
        return $query->select([
            'groups.id',
            'groups.owner_id',
            'groups.owner_user_id',
            'groups.name',
            'groups.slug',
            'groups.description',
            'groups.type',
            'groups.privacy',
            'groups.created_at',
        ]);
    }

    public static function paginateSearchResults(?User $viewer, string $term, int $perPage = 15): LengthAwarePaginator
    {
        return self::query()
            ->searchResultColumns()
            ->visible($viewer)
            ->search($term !== '' ? $term : null)
            ->latest('groups.created_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    public static function paginateIndexResults(
        User $viewer,
        string $search,
        string $privacy,
        string $sort,
        int $perPage = 12
    ): LengthAwarePaginator {
        $viewerId = (int) $viewer->getKey();
        $ownerColumn = self::ownerColumnName();

        $query = self::query()
            ->with('owner:id,name,username')
            ->where(function (Builder $visibilityQuery) use ($viewerId, $ownerColumn): void {
                $visibilityQuery
                    ->where(function (Builder $discoverableQuery): void {
                        $discoverableQuery
                            ->where(function (Builder $privacyQuery): void {
                                $privacyQuery
                                    ->whereNull('privacy')
                                    ->orWhere('privacy', '!=', 'secret');
                            })
                            ->where(function (Builder $typeQuery): void {
                                $typeQuery
                                    ->whereNull('type')
                                    ->orWhere('type', '!=', 'secret');
                            });
                    })
                    ->orWhere($ownerColumn, $viewerId)
                    ->orWhereHas('members', function (Builder $memberQuery) use ($viewerId): void {
                        $memberQuery
                            ->forUser($viewerId)
                            ->active();
                    });
            });

        if (in_array($privacy, ['public', 'private', 'secret'], true)) {
            $query->where(function (Builder $privacyQuery) use ($privacy): void {
                $privacyQuery
                    ->where('privacy', $privacy)
                    ->orWhere(function (Builder $fallbackTypeQuery) use ($privacy): void {
                        $fallbackTypeQuery
                            ->whereNull('privacy')
                            ->where('type', $privacy);
                    });
            });
        }

        if ($privacy === 'joined') {
            $query->whereHas('members', function (Builder $memberQuery) use ($viewerId): void {
                $memberQuery
                    ->forUser($viewerId)
                    ->active();
            });
        }

        if ($privacy === 'owned') {
            $query->where($ownerColumn, $viewerId);
        }

        if ($search !== '') {
            $query->where(function (Builder $searchQuery) use ($search): void {
                $searchQuery
                    ->where('groups.name', 'like', "%{$search}%")
                    ->orWhere('groups.description', 'like', "%{$search}%")
                    ->orWhere('groups.slug', 'like', "%{$search}%");
            });
        }

        if ($sort === 'name') {
            $query->orderBy('groups.name');
        } elseif ($sort === 'members') {
            $query->orderByDesc('groups.members_count')
                ->orderByDesc('groups.created_at');
        } else {
            $query->latest('groups.created_at');
        }

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * @param  Collection<int, int>  $groupIds
     * @return Collection<int, GroupMember>
     */
    public static function membershipMapForUserAndGroups(User $viewer, Collection $groupIds): Collection
    {
        return GroupMember::membershipMapForUserAndGroups((int) $viewer->getKey(), $groupIds);
    }

    /**
     * @return Collection<int, self>
     */
    public static function eventFilterOptions(int $limit = 100): Collection
    {
        return self::query()
            ->select('id', 'name')
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, self>
     */
    public static function eventCreatableForUser(int $viewerId, ?string $ownerColumn = null): Collection
    {
        if ($viewerId <= 0) {
            return collect();
        }

        $ownerColumn ??= self::ownerColumnName();

        return self::query()
            ->select('groups.id', 'groups.name')
            ->where(function (Builder $query) use ($ownerColumn, $viewerId): void {
                $query->whereHas('members', function (Builder $memberQuery) use ($viewerId): void {
                    $memberQuery
                        ->forUser($viewerId)
                        ->active();
                });

                $query->orWhere($ownerColumn, $viewerId);
            })
            ->orderBy('groups.name')
            ->get();
    }

    public static function userOwnsGroupById(int $groupId, int $viewerId, ?string $ownerColumn = null): bool
    {
        if ($groupId <= 0 || $viewerId <= 0) {
            return false;
        }

        $ownerColumn ??= self::ownerColumnName();

        return self::query()
            ->whereKey($groupId)
            ->where($ownerColumn, $viewerId)
            ->exists();
    }

    public static function userCanCreateEventInGroup(int $groupId, int $viewerId, ?string $ownerColumn = null): bool
    {
        if ($groupId <= 0) {
            return true;
        }

        if (self::userOwnsGroupById($groupId, $viewerId, $ownerColumn)) {
            return true;
        }

        return GroupMember::query()
            ->forGroup($groupId)
            ->forUser($viewerId)
            ->active()
            ->exists();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
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

    public function avatarUrl(): string
    {
        $mediaUrl = $this->getFirstMediaUrl(self::MEDIA_COLLECTION_AVATAR, self::MEDIA_CONVERSION_THUMB);

        if ($mediaUrl !== '') {
            return $mediaUrl;
        }

        if (filled((string) $this->avatar)) {
            return Storage::disk('public')->url(ltrim((string) $this->avatar, '/'));
        }

        if (filled((string) $this->avatar_path)) {
            return (string) $this->avatar_path;
        }

        return '/images/default-group-avatar.png';
    }

    public function coverUrl(): string
    {
        $mediaUrl = $this->getFirstMediaUrl(self::MEDIA_COLLECTION_COVER, self::MEDIA_CONVERSION_LARGE);

        if ($mediaUrl !== '') {
            return $mediaUrl;
        }

        if (filled((string) $this->cover_image)) {
            return Storage::disk('public')->url(ltrim((string) $this->cover_image, '/'));
        }

        if (filled((string) $this->cover_image_path)) {
            return (string) $this->cover_image_path;
        }

        return '/images/default-group-cover.png';
    }

    public function isMember(User $user): bool
    {
        return $this->members()
            ->where('user_id', $user->getKey())
            ->where(function (Builder $statusQuery): void {
                if (Schema::hasColumn('group_members', 'status')) {
                    $statusQuery
                        ->whereNull('status')
                        ->orWhereIn('status', ['active', 'accepted']);
                }
            })
            ->exists();
    }

    public function membershipForUserId(int $userId): ?GroupMember
    {
        return GroupMember::firstForGroupAndUser((int) $this->getKey(), $userId);
    }

    public function isActiveMembership(?GroupMember $membership): bool
    {
        if (! $membership instanceof GroupMember) {
            return false;
        }

        return $membership->status === null
            || in_array((string) ($membership->status?->value ?? ''), GroupMemberStatus::activeValues(), true);
    }

    public function activeMembersCount(): int
    {
        return (int) $this->memberships()->active()->count();
    }

    public function syncMembersCount(): void
    {
        if (! Schema::hasColumn('groups', 'members_count')) {
            return;
        }

        $this->forceFill([
            'members_count' => $this->activeMembersCount(),
        ])->save();
    }

    public function paginateActiveMembers(int $perPage = 20, string $pageName = 'members_page'): LengthAwarePaginator
    {
        return GroupMember::paginateActiveForGroup($this, $perPage, $pageName);
    }

    /**
     * @return Collection<int, GroupMember>
     */
    public function pendingMembers(): Collection
    {
        return GroupMember::pendingForGroup($this);
    }

    public function paginateEventsForShow(string $startColumn = 'start_at', int $perPage = 12, string $pageName = 'events_page'): LengthAwarePaginator
    {
        return $this->events()
            ->orderBy($startColumn)
            ->paginate($perPage, ['*'], $pageName)
            ->withQueryString();
    }

    public function eventsCount(): int
    {
        return (int) $this->events()->count();
    }

    public function normalizedPrivacy(): string
    {
        $privacy = strtolower((string) ($this->privacy ?: $this->type ?: 'public'));

        return in_array($privacy, ['public', 'private', 'secret'], true)
            ? $privacy
            : 'public';
    }

    public function normalizedStatus(): string
    {
        $status = strtolower((string) ($this->status ?: 'active'));

        return in_array($status, ['active', 'archived'], true)
            ? $status
            : 'active';
    }

    public function isArchived(): bool
    {
        return $this->normalizedStatus() === 'archived' || $this->archived_at !== null;
    }

    public function archive(): bool
    {
        return $this->forceFill([
            'status' => 'archived',
            'archived_at' => $this->archived_at ?: now(),
        ])->save();
    }

    public function unarchive(): bool
    {
        return $this->forceFill([
            'status' => 'active',
            'archived_at' => null,
        ])->save();
    }

    public function isOwner(User $user): bool
    {
        return (int) $this->owner_id === (int) $user->getKey()
            || (int) ($this->owner_user_id ?? 0) === (int) $user->getKey();
    }

    public function memberRole(User $user): ?string
    {
        return $this->members()
            ->where('user_id', $user->getKey())
            ->value('role');
    }

    public function isBanned(User $user): bool
    {
        return $this->bans()
            ->where('user_id', $user->getKey())
            ->exists();
    }

    public function hasPendingRequest(User $user): bool
    {
        return $this->joinRequests()
            ->where('user_id', $user->getKey())
            ->where('status', 'pending')
            ->exists();
    }

    public function canManage(User $user): bool
    {
        if ($this->isOwner($user)) {
            return true;
        }

        return $this->members()
            ->where('user_id', $user->getKey())
            ->where('role', 'admin')
            ->where(function (Builder $statusQuery): void {
                if (Schema::hasColumn('group_members', 'status')) {
                    $statusQuery
                        ->whereNull('status')
                        ->orWhereIn('status', ['active', 'accepted']);
                }
            })
            ->exists();
    }

    public function canModerate(User $user): bool
    {
        if ($this->isOwner($user)) {
            return true;
        }

        return $this->members()
            ->where('user_id', $user->getKey())
            ->whereIn('role', ['admin', 'moderator'])
            ->where(function (Builder $statusQuery): void {
                if (Schema::hasColumn('group_members', 'status')) {
                    $statusQuery
                        ->whereNull('status')
                        ->orWhereIn('status', ['active', 'accepted']);
                }
            })
            ->exists();
    }

    public function getAvatarUrlAttribute(): string
    {
        return $this->avatarUrl();
    }

    public function getCoverUrlAttribute(): string
    {
        return $this->coverUrl();
    }

    public function getCoverPhotoUrlAttribute(): string
    {
        return $this->coverUrl();
    }

    public function getProfilePhotoUrlAttribute(): string
    {
        return $this->avatarUrl();
    }

    public function addMember(User $user, string $role = 'member', string $status = 'active'): bool
    {
        $attributes = [
            'group_id' => $this->getKey(),
            'user_id' => $user->getKey(),
        ];

        $values = [
            'role' => $role,
            'joined_at' => now(),
        ];

        if (Schema::hasColumn('group_members', 'status')) {
            $values['status'] = $status;
        }

        $this->members()->updateOrCreate($attributes, $values);

        return true;
    }

    public function removeMember(User $user): bool
    {
        return (bool) $this->members()->where('user_id', $user->getKey())->delete();
    }

    protected static function generateUniqueSlug(string $seed, ?int $ignoreId = null): string
    {
        return app(GroupSlugService::class)->generateUnique($seed, $ignoreId);
    }

    protected function syncOwnerColumns(): void
    {
        $ownerId = $this->getAttribute('owner_id') ?? $this->getAttribute('owner_user_id');

        if ($ownerId !== null) {
            $this->setAttribute('owner_id', (int) $ownerId);

            if (Schema::hasColumn('groups', 'owner_user_id')) {
                $this->setAttribute('owner_user_id', (int) $ownerId);
            }
        }
    }

    protected static function ownerColumnName(): string
    {
        return Schema::hasColumn('groups', 'owner_user_id')
            ? 'owner_user_id'
            : 'owner_id';
    }
}
