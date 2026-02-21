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
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

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

    /**
     * @var list<string>
     */
    protected $fillable = [
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
    ];

    /**
     * @var list<string>
     */
    protected $appends = [
        'cover_photo_url',
        'avatar_url',
        'profile_photo_url',
    ];

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
        if (! $term) {
            return $query;
        }

        return $query->where(function (Builder $subQuery) use ($term): void {
            $subQuery
                ->where('title', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%")
                ->orWhere('location_text', 'like', "%{$term}%");
        });
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
