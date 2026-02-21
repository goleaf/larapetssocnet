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
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Group extends Model implements HasMedia
{
    use HasCounterCache;
    use HasFactory;
    use HasSlug;
    use InteractsWithMedia;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'owner_user_id',
        'name',
        'slug',
        'description',
        'rules',
        'type',
        'location',
        'website',
        'privacy',
        'avatar_path',
        'cover_image_path',
        'members_count',
        'posts_count',
    ];

    /**
     * @var list<string>
     */
    protected $appends = [
        'avatar_url',
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

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('avatar')->singleFile();
        $this->addMediaCollection('cover')->singleFile();
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(GroupMember::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'group_members', 'group_id', 'user_id')
            ->withPivot(['role', 'status', 'joined_at', 'invited_by'])
            ->withTimestamps();
    }

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'group_posts', 'group_id', 'post_id')
            ->withPivot('added_by_user_id')
            ->withTimestamps();
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query->where(function (Builder $subQuery): void {
            $subQuery
                ->whereNull('privacy')
                ->orWhere('privacy', 'public')
                ->orWhere('type', 'public');
        });
    }

    public function scopePrivate(Builder $query): Builder
    {
        return $query->where(function (Builder $subQuery): void {
            $subQuery
                ->whereIn('privacy', ['private', 'secret'])
                ->orWhereIn('type', ['private', 'secret']);
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

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function isMember(User $user): bool
    {
        return $this->members()->whereKey($user->getKey())->exists();
    }

    public function memberRole(User $user): ?string
    {
        return $this->memberships()
            ->where('user_id', $user->getKey())
            ->value('role');
    }

    public function addMember(User $user, string $role = 'member', string $status = 'active'): bool
    {
        return DB::transaction(function () use ($user, $role, $status): bool {
            $membership = $this->memberships()
                ->where('user_id', $user->getKey())
                ->lockForUpdate()
                ->first();

            if ($membership) {
                $previouslyActive = $membership->status === 'active';
                $membership->forceFill([
                    'role' => $role,
                    'status' => $status,
                    'joined_at' => $membership->joined_at ?: now(),
                ])->save();

                if (! $previouslyActive && $status === 'active') {
                    $this->incrementCounter('members_count');
                }

                return true;
            }

            $this->memberships()->create([
                'user_id' => $user->getKey(),
                'role' => $role,
                'status' => $status,
                'joined_at' => now(),
            ]);

            if ($status === 'active') {
                $this->incrementCounter('members_count');
            }

            return true;
        });
    }

    public function removeMember(User $user): bool
    {
        return DB::transaction(function () use ($user): bool {
            $membership = $this->memberships()
                ->where('user_id', $user->getKey())
                ->lockForUpdate()
                ->first();

            if (! $membership) {
                return false;
            }

            $wasActive = $membership->status === 'active';
            $deleted = (bool) $membership->delete();

            if ($deleted && $wasActive) {
                $this->decrementCounter('members_count');
            }

            return $deleted;
        });
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

            return (string) ($this->cover_image_path ?: $this->avatar_url);
        });
    }

    protected function profilePhotoUrl(): Attribute
    {
        return Attribute::get(fn (): string => $this->avatar_url);
    }
}
