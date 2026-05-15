<?php

namespace App\Models\Messaging;

use App\Enums\MessageStatus;
use App\Models\Identity\User;
use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[UseFactory(MessageFactory::class)]
#[Fillable([
    'conversation_id',
    'sender_id',
    'receiver_id',
    'body',
    'status',
    'is_read',
    'read_at',
])]
class Message extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => MessageStatus::class,
            'is_read' => 'boolean',
            'read_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    /**
     * Scope to messages where a user is sender or receiver.
     *
     * Includes a legacy fallback for records that only have conversation participants.
     */
    public function scopeForUser(Builder $query, User|int $userId): Builder
    {
        $resolvedUserId = $userId instanceof User ? (int) $userId->getKey() : $userId;

        return $query
            ->select(['messages.*'])
            ->where(function (Builder $userQuery) use ($resolvedUserId): void {
                $userQuery
                    ->where('messages.sender_id', $resolvedUserId)
                    ->orWhere('messages.receiver_id', $resolvedUserId)
                    ->orWhere(function (Builder $legacyQuery) use ($resolvedUserId): void {
                        $legacyQuery
                            ->whereNull('messages.receiver_id')
                            ->whereHas('conversation', function (Builder $conversationQuery) use ($resolvedUserId): void {
                                $conversationQuery
                                    ->where('user_one_id', $resolvedUserId)
                                    ->orWhere('user_two_id', $resolvedUserId);
                            });
                    });
            });
    }

    /**
     * Scope to messages between two specific users.
     */
    public function scopeBetween(Builder $query, User|int $userA, User|int $userB): Builder
    {
        $userAId = $userA instanceof User ? (int) $userA->getKey() : $userA;
        $userBId = $userB instanceof User ? (int) $userB->getKey() : $userB;

        return $query->where(function (Builder $threadQuery) use ($userAId, $userBId): void {
            $threadQuery
                ->where(function (Builder $directQuery) use ($userAId, $userBId): void {
                    $directQuery
                        ->where('messages.sender_id', $userAId)
                        ->where('messages.receiver_id', $userBId);
                })
                ->orWhere(function (Builder $directQuery) use ($userAId, $userBId): void {
                    $directQuery
                        ->where('messages.sender_id', $userBId)
                        ->where('messages.receiver_id', $userAId);
                })
                ->orWhere(function (Builder $legacyQuery) use ($userAId, $userBId): void {
                    $legacyQuery
                        ->whereNull('messages.receiver_id')
                        ->whereHas('conversation', function (Builder $conversationQuery) use ($userAId, $userBId): void {
                            $conversationQuery
                                ->where(function (Builder $firstDirection) use ($userAId, $userBId): void {
                                    $firstDirection
                                        ->where('user_one_id', $userAId)
                                        ->where('user_two_id', $userBId);
                                })
                                ->orWhere(function (Builder $secondDirection) use ($userAId, $userBId): void {
                                    $secondDirection
                                        ->where('user_one_id', $userBId)
                                        ->where('user_two_id', $userAId);
                                });
                        });
                });
        });
    }

    public function scopeInThread(Builder $query, User|int $userA, User|int $userB): Builder
    {
        return $query
            ->select(['messages.*'])
            ->between($userA, $userB);
    }

    public function scopeUnread(Builder $query, User|int $userId): Builder
    {
        $resolvedUserId = $userId instanceof User ? (int) $userId->getKey() : $userId;

        return $query
            ->select(['messages.*'])
            ->where(function (Builder $unreadQuery) use ($resolvedUserId): void {
                $unreadQuery
                    ->where(function (Builder $directUnreadQuery) use ($resolvedUserId): void {
                        $directUnreadQuery
                            ->where('messages.receiver_id', $resolvedUserId)
                            ->whereNull('messages.read_at');
                    })
                    ->orWhere(function (Builder $legacyUnreadQuery) use ($resolvedUserId): void {
                        $legacyUnreadQuery
                            ->whereNull('messages.receiver_id')
                            ->whereNull('messages.read_at')
                            ->where('messages.sender_id', '!=', $resolvedUserId)
                            ->whereHas('conversation', function (Builder $conversationQuery) use ($resolvedUserId): void {
                                $conversationQuery
                                    ->where('user_one_id', $resolvedUserId)
                                    ->orWhere('user_two_id', $resolvedUserId);
                            });
                    });
            });
    }

    public function partnerIdFor(User|int $userId): ?int
    {
        $resolvedUserId = $userId instanceof User ? (int) $userId->getKey() : $userId;

        if ((int) $this->sender_id === $resolvedUserId && $this->receiver_id) {
            return (int) $this->receiver_id;
        }

        if ((int) $this->receiver_id === $resolvedUserId) {
            return (int) $this->sender_id;
        }

        return null;
    }

    protected function displayBody(): Attribute
    {
        return Attribute::get(function (): string {
            $body = preg_replace('/\s+/', ' ', (string) ($this->body ?? ''));

            return trim((string) $body);
        });
    }
}
