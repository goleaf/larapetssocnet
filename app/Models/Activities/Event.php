<?php

namespace App\Models\Activities;

use App\Models\Groups\Group;
use App\Models\Identity\User;
use App\Models\Moderation\Report;
use App\Support\Search\SearchInput;
use App\Traits\HasCounterCache;
use Database\Factories\EventFactory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

#[UseFactory(EventFactory::class)]
#[Appends([
    'cover_photo_url',
    'avatar_url',
    'profile_photo_url',
])]
#[Fillable([
    'group_id',
    'creator_user_id',
    'title',
    'description',
    'location_text',
    'start_at',
    'end_at',
    'status',
    'cover_image_path',
    'attendees_count',
])]
class Event extends Model implements HasMedia
{
    use HasCounterCache;
    use HasFactory;
    use InteractsWithMedia;
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const ATTENDEE_GOING = 'going';

    public const ATTENDEE_INTERESTED = 'interested';

    public const ATTENDEE_DECLINED = 'declined';

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'attendees_count' => 'integer',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('cover')->singleFile();
        $this->addMediaCollection('gallery');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_user_id');
    }

    public function attendees(): HasMany
    {
        return $this->hasMany(EventAttendee::class);
    }

    public function attendingUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_attendees', 'event_id', 'user_id')
            ->withPivot(['status', 'responded_at'])
            ->withTimestamps();
    }

    public function reports(): MorphMany
    {
        return $this->morphMany(Report::class, 'reportable');
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('start_at', '>=', now())->orderBy('start_at');
    }

    public function scopePast(Builder $query): Builder
    {
        return $query->where('start_at', '<', now())->orderByDesc('start_at');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where(fn (Builder $subQuery) => $subQuery->whereNull('status')->orWhere('status', self::STATUS_PUBLISHED));
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = SearchInput::normalize($term);

        if ($term === '') {
            return $query;
        }

        $pattern = SearchInput::containsPattern($term);

        return $query->where(function (Builder $subQuery) use ($pattern): void {
            $subQuery
                ->where('title', 'like', $pattern)
                ->orWhere('description', 'like', $pattern)
                ->orWhere('location_text', 'like', $pattern);
        });
    }

    public function scopeSearchResultColumns(Builder $query): Builder
    {
        return $query->select([
            'events.id',
            'events.group_id',
            'events.creator_user_id',
            'events.title',
            'events.description',
            'events.location_text',
            'events.start_at',
            'events.status',
            'events.created_at',
        ]);
    }

    public static function paginateSearchResults(string $term, int $perPage = 15): LengthAwarePaginator
    {
        return self::query()
            ->searchResultColumns()
            ->search($term !== '' ? $term : null)
            ->latest('events.created_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    public static function paginateIndexResults(
        ?User $viewer,
        string $search,
        string $scope,
        int $groupId,
        string $startColumn = 'start_at',
        ?string $statusColumn = 'status',
        string $locationColumn = 'location_text',
        string $creatorColumn = 'creator_user_id',
        ?string $groupPrivacyColumn = 'privacy',
        ?string $groupOwnerColumn = 'owner_user_id',
        int $perPage = 12
    ): LengthAwarePaginator {
        $query = self::query()
            ->leftJoin('groups', 'groups.id', '=', 'events.group_id')
            ->leftJoin('users as creators', 'creators.id', '=', "events.{$creatorColumn}")
            ->select([
                'events.*',
                'groups.name as group_name',
                'groups.slug as group_slug',
                'creators.name as creator_name',
                "events.{$locationColumn} as event_location",
            ]);

        if ($groupPrivacyColumn) {
            $query->where(function (Builder $visibilityQuery) use ($groupPrivacyColumn, $groupOwnerColumn, $viewer): void {
                $visibilityQuery
                    ->whereNull('events.group_id')
                    ->orWhereNull("groups.{$groupPrivacyColumn}")
                    ->orWhere("groups.{$groupPrivacyColumn}", '!=', 'secret');

                if ($viewer instanceof User) {
                    $viewerId = (int) $viewer->getKey();

                    $visibilityQuery
                        ->orWhereHas('group.members', function (Builder $memberQuery) use ($viewerId): void {
                            $memberQuery->forUser($viewerId);
                        });

                    if ($groupOwnerColumn) {
                        $visibilityQuery->orWhere("groups.{$groupOwnerColumn}", $viewerId);
                    }
                }
            });
        }

        $search = SearchInput::normalize($search);

        if ($search !== '') {
            $pattern = SearchInput::containsPattern($search);

            $query->where(function (Builder $searchQuery) use ($locationColumn, $pattern): void {
                $searchQuery
                    ->where('events.title', 'like', $pattern)
                    ->orWhere('events.description', 'like', $pattern)
                    ->orWhere("events.{$locationColumn}", 'like', $pattern);
            });
        }

        if ($groupId > 0) {
            $query->where('events.group_id', $groupId);
        }

        if ($scope === 'mine' && $viewer) {
            $query->where("events.{$creatorColumn}", $viewer->getKey());
        }

        if ($scope === 'upcoming') {
            $query->where("events.{$startColumn}", '>=', now());

            if ($statusColumn) {
                $query->where("events.{$statusColumn}", '!=', 'cancelled');
            }

            $query->orderBy("events.{$startColumn}");
        } elseif ($scope === 'past') {
            $query->where("events.{$startColumn}", '<', now())
                ->orderByDesc("events.{$startColumn}");
        } elseif ($scope === 'cancelled' && $statusColumn) {
            $query->where("events.{$statusColumn}", 'cancelled')
                ->orderByDesc("events.{$startColumn}");
        } else {
            $query->orderBy("events.{$startColumn}");
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public static function findFromRouteToken(string $event): ?self
    {
        if (! ctype_digit($event)) {
            return null;
        }

        return self::query()->whereKey((int) $event)->first();
    }

    /**
     * @return Collection<int, EventAttendee>
     */
    public function recentAttendees(int $limit = 50): Collection
    {
        return $this->attendees()
            ->with('user:id,name,username')
            ->latest('responded_at')
            ->limit($limit)
            ->get();
    }

    public function rsvpStatusForUser(int $userId): ?string
    {
        return $this->attendees()
            ->where('user_id', $userId)
            ->value('status');
    }

    public function goingAttendeesCount(): int
    {
        return (int) $this->attendees()->going()->count();
    }

    public function creatorForDisplay(?int $creatorId): ?User
    {
        if (! $creatorId) {
            return null;
        }

        return User::query()->find($creatorId, ['id', 'name', 'username']);
    }

    public function upsertAttendee(int $userId, string $status): EventAttendee
    {
        return $this->attendees()->updateOrCreate(
            [
                'event_id' => (int) $this->getKey(),
                'user_id' => $userId,
            ],
            [
                'status' => $status,
                'responded_at' => now(),
            ]
        );
    }

    public function toggleRsvpStatus(
        int $userId,
        string $requestedStatus,
        ?int $maxAttendees,
        bool $syncAttendeesCount,
        bool $syncInterestedCount
    ): string {
        return DB::transaction(function () use ($maxAttendees, $requestedStatus, $syncAttendeesCount, $syncInterestedCount, $userId): string {
            $eventId = (int) $this->getKey();

            self::query()
                ->whereKey($eventId)
                ->lockForUpdate()
                ->firstOrFail();

            $attendee = EventAttendee::query()
                ->where('event_id', $eventId)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->first();

            if ($attendee && self::normalizedRsvpStatus((string) $attendee->status) === $requestedStatus) {
                $attendee->delete();
                self::syncAttendeesCounters($eventId, $syncAttendeesCount, $syncInterestedCount);

                return 'removed';
            }

            if ($requestedStatus === self::ATTENDEE_GOING && $maxAttendees !== null) {
                $goingCount = EventAttendee::query()
                    ->where('event_id', $eventId)
                    ->going()
                    ->when($attendee, fn (Builder $query) => $query->where('user_id', '!=', $userId))
                    ->count();

                if ($goingCount >= $maxAttendees) {
                    throw ValidationException::withMessages([
                        'status' => 'RSVP failed. This event has reached maximum attendees.',
                    ]);
                }
            }

            $storedStatus = self::storedRsvpStatus($requestedStatus);

            if ($attendee) {
                $attendee->forceFill([
                    'status' => $storedStatus,
                    'responded_at' => now(),
                ])->save();
            } else {
                $this->upsertAttendee($userId, $storedStatus);
            }

            self::syncAttendeesCounters($eventId, $syncAttendeesCount, $syncInterestedCount);

            return 'updated';
        });
    }

    public static function syncAttendeesCounters(int $eventId, bool $syncAttendeesCount, bool $syncInterestedCount): void
    {
        $payload = [];

        if ($syncAttendeesCount) {
            $payload['attendees_count'] = (int) EventAttendee::query()
                ->where('event_id', $eventId)
                ->going()
                ->count();
        }

        if ($syncInterestedCount) {
            $payload['interested_count'] = (int) EventAttendee::query()
                ->where('event_id', $eventId)
                ->whereIn('status', ['maybe', self::ATTENDEE_INTERESTED])
                ->count();
        }

        if ($payload === []) {
            return;
        }

        self::query()
            ->whereKey($eventId)
            ->update($payload);
    }

    protected static function normalizedRsvpStatus(string $status): string
    {
        return match ($status) {
            self::ATTENDEE_GOING => self::ATTENDEE_GOING,
            self::ATTENDEE_INTERESTED, 'maybe' => 'maybe',
            self::ATTENDEE_DECLINED, 'not_going' => 'not_going',
            default => 'not_going',
        };
    }

    protected static function storedRsvpStatus(string $status): string
    {
        return match ($status) {
            self::ATTENDEE_GOING => self::ATTENDEE_GOING,
            'maybe' => self::ATTENDEE_INTERESTED,
            default => 'not_going',
        };
    }

    /**
     * @return array{upcoming: Collection<int, self>, past: Collection<int, self>}
     */
    public static function attendeeBucketsForProfile(User $user, int $upcomingLimit = 20, int $pastLimit = 5): array
    {
        $baseQuery = self::query()
            ->whereHas('attendees', fn (Builder $query) => $query
                ->where('user_id', $user->getKey())
                ->whereIn('status', [self::ATTENDEE_GOING, self::ATTENDEE_INTERESTED]))
            ->with('creator');

        return [
            'upcoming' => (clone $baseQuery)->upcoming()->limit($upcomingLimit)->get(),
            'past' => (clone $baseQuery)->past()->limit($pastLimit)->get(),
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'id';
    }

    public function isUpcoming(): bool
    {
        return $this->start_at !== null && $this->start_at->isFuture();
    }

    public function isPast(): bool
    {
        return $this->start_at !== null && $this->start_at->isPast();
    }

    public function isFull(): bool
    {
        return false;
    }

    public function respond(User $user, string $status = self::ATTENDEE_GOING): EventAttendee
    {
        return DB::transaction(function () use ($status, $user): EventAttendee {
            $attendee = $this->attendees()
                ->where('user_id', $user->getKey())
                ->lockForUpdate()
                ->first();

            if (! $attendee) {
                $attendee = $this->attendees()->create([
                    'user_id' => $user->getKey(),
                    'status' => $status,
                    'responded_at' => now(),
                ]);

                $this->incrementStatusCounter($status);

                return $attendee;
            }

            $oldStatus = $attendee->status;

            if ($oldStatus === $status) {
                return $attendee;
            }

            $attendee->forceFill([
                'status' => $status,
                'responded_at' => now(),
            ])->save();

            $this->decrementStatusCounter((string) $oldStatus);
            $this->incrementStatusCounter($status);

            return $attendee;
        });
    }

    public function removeAttendee(User $user): bool
    {
        return DB::transaction(function () use ($user): bool {
            $attendee = $this->attendees()
                ->where('user_id', $user->getKey())
                ->lockForUpdate()
                ->first();

            if (! $attendee) {
                return false;
            }

            $status = (string) $attendee->status;
            $deleted = (bool) $attendee->delete();

            if ($deleted) {
                $this->decrementStatusCounter($status);
            }

            return $deleted;
        });
    }

    protected function incrementStatusCounter(string $status): void
    {
        $column = $this->statusCounterColumn($status);

        if ($column) {
            $this->incrementCounter($column);
        }
    }

    protected function decrementStatusCounter(string $status): void
    {
        $column = $this->statusCounterColumn($status);

        if ($column) {
            $this->decrementCounter($column);
        }
    }

    protected function statusCounterColumn(string $status): ?string
    {
        return match ($status) {
            self::ATTENDEE_GOING => 'attendees_count',
            self::ATTENDEE_INTERESTED => null,
            default => null,
        };
    }

    protected function coverPhotoUrl(): Attribute
    {
        return Attribute::get(function (): string {
            $mediaUrl = $this->getFirstMediaUrl('cover');

            if ($mediaUrl !== '') {
                return $mediaUrl;
            }

            return (string) ($this->cover_image_path ?: '');
        });
    }

    protected function avatarUrl(): Attribute
    {
        return Attribute::get(fn (): string => $this->cover_photo_url);
    }

    protected function profilePhotoUrl(): Attribute
    {
        return Attribute::get(fn (): string => $this->cover_photo_url);
    }
}
