<?php

namespace App\Models;

use App\Traits\HasCounterCache;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Group extends Model implements HasMedia
{
    use HasCounterCache;
    use HasFactory;
    use InteractsWithMedia;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'owner_id',
        'owner_user_id',
        'name',
        'slug',
        'description',
        'avatar',
        'avatar_path',
        'cover_image',
        'cover_image_path',
        'type',
        'privacy',
        'species_focus',
        'species',
        'rules',
        'location',
        'website',
        'members_count',
        'posts_count',
    ];

    /**
     * @var list<string>
     */
    protected $appends = [
        'avatar_url',
        'cover_url',
        'cover_photo_url',
        'profile_photo_url',
    ];

    protected function casts(): array
    {
        return [
            'members_count' => 'integer',
            'posts_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $group): void {
            $group->syncOwnerColumns();

            if (! $group->exists || $group->isDirty('name') || blank($group->slug)) {
                $group->slug = static::generateUniqueSlug(
                    (string) ($group->name ?: $group->slug),
                    $group->exists ? (int) $group->getKey() : null,
                );
            }

            if (blank($group->type)) {
                $group->type = 'public';
            }

            if (blank($group->species_focus)) {
                $group->species_focus = 'all';
            }
        });
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('avatar')->singleFile();
        $this->addMediaCollection('cover')->singleFile();
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

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function scopeVisible(Builder $query, ?User $user): Builder
    {
        return $query->where(function (Builder $visibilityQuery) use ($user): void {
            $visibilityQuery
                ->where('type', '!=', 'secret')
                ->orWhereNull('type');

            if ($user) {
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

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function avatarUrl(): string
    {
        $mediaUrl = $this->getFirstMediaUrl('avatar');

        if ($mediaUrl !== '') {
            return $mediaUrl;
        }

        if (filled((string) $this->avatar)) {
            return asset('storage/'.ltrim((string) $this->avatar, '/'));
        }

        if (filled((string) $this->avatar_path)) {
            return (string) $this->avatar_path;
        }

        return '/images/default-group-avatar.png';
    }

    public function coverUrl(): string
    {
        $mediaUrl = $this->getFirstMediaUrl('cover');

        if ($mediaUrl !== '') {
            return $mediaUrl;
        }

        if (filled((string) $this->cover_image)) {
            return asset('storage/'.ltrim((string) $this->cover_image, '/'));
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
        $base = Str::slug($seed);

        if ($base === '') {
            $base = 'group';
        }

        $slug = $base;
        $suffix = 1;

        while (static::query()
            ->withTrashed()
            ->where('slug', $slug)
            ->when($ignoreId, fn (Builder $query): Builder => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
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
}
