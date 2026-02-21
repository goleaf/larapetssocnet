<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventAttendee extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'event_id',
        'user_id',
        'status',
        'responded_at',
        'checked_in_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'responded_at' => 'datetime',
            'checked_in_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeGoing(Builder $query): Builder
    {
        return $query->where('status', Event::ATTENDEE_GOING);
    }

    public function scopeInterested(Builder $query): Builder
    {
        return $query->where('status', Event::ATTENDEE_INTERESTED);
    }

    public function scopeDeclined(Builder $query): Builder
    {
        return $query->where('status', Event::ATTENDEE_DECLINED);
    }

    public function scopeCheckedIn(Builder $query): Builder
    {
        return $query->whereNotNull('checked_in_at');
    }

    public function hasCheckedIn(): bool
    {
        return $this->checked_in_at !== null;
    }

    public function markCheckedIn(): void
    {
        if ($this->checked_in_at) {
            return;
        }

        $this->forceFill([
            'checked_in_at' => now(),
        ])->save();
    }
}
