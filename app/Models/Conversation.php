<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Conversation extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'listing_id',
        'user_one_id',
        'user_two_id',
        'blocked_by',
        'last_message_at',
        'last_message_preview',
        'unread_count_user_one',
        'unread_count_user_two',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'blocked_by' => 'integer',
            'unread_count_user_one' => 'integer',
            'unread_count_user_two' => 'integer',
        ];
    }

    public function userOne(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_one_id');
    }

    public function userTwo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_two_id');
    }

    public function blockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_by');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at');
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany('created_at');
    }

    public function otherUser(User $user): ?User
    {
        if (! $this->isParticipant($user)) {
            return null;
        }

        if ((int) $this->user_one_id === (int) $user->getKey()) {
            return $this->relationLoaded('userTwo') ? $this->userTwo : $this->userTwo()->first();
        }

        return $this->relationLoaded('userOne') ? $this->userOne : $this->userOne()->first();
    }

    public function isBlocked(): bool
    {
        return $this->blocked_by !== null;
    }

    public function isBlockedBy(User|int $user): bool
    {
        $userId = $user instanceof User ? (int) $user->getKey() : (int) $user;

        return (int) ($this->blocked_by ?? 0) === $userId;
    }

    public function unreadCountFor(User|int $user): int
    {
        $userId = $user instanceof User ? (int) $user->getKey() : (int) $user;

        if ($userId === (int) $this->user_one_id) {
            return (int) ($this->unread_count_user_one ?? 0);
        }

        if ($userId === (int) $this->user_two_id) {
            return (int) ($this->unread_count_user_two ?? 0);
        }

        return 0;
    }

    public function isParticipant(User|int $user): bool
    {
        $userId = $user instanceof User ? (int) $user->getKey() : (int) $user;

        return $userId === (int) $this->user_one_id || $userId === (int) $this->user_two_id;
    }

    public function scopeForUser(Builder $query, User|int $user): Builder
    {
        $userId = $user instanceof User ? (int) $user->getKey() : (int) $user;

        return $query->where(function (Builder $conversationQuery) use ($userId): void {
            $conversationQuery
                ->where('user_one_id', $userId)
                ->orWhere('user_two_id', $userId);
        });
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderByRaw('last_message_at IS NULL ASC')
            ->orderByDesc('last_message_at')
            ->orderByDesc('updated_at');
    }
}
