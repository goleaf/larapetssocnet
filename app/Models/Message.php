<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'conversation_id',
        'sender_id',
        'body',
        'is_read',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
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

    /**
     * Scope to messages belonging to conversations that include the given user.
     */
    public function scopeForUser(Builder $query, User|int $user): Builder
    {
        $userId = $user instanceof User ? (int) $user->getKey() : (int) $user;

        return $query->whereHas('conversation', function (Builder $conversationQuery) use ($userId): void {
            $conversationQuery->where('user_one_id', $userId)->orWhere('user_two_id', $userId);
        });
    }

    /**
     * Scope to messages in the conversation between two specific users.
     */
    public function scopeBetween(Builder $query, User|int $userA, User|int $userB): Builder
    {
        $userAId = $userA instanceof User ? (int) $userA->getKey() : (int) $userA;
        $userBId = $userB instanceof User ? (int) $userB->getKey() : (int) $userB;

        return $query->whereHas('conversation', function (Builder $conversationQuery) use ($userAId, $userBId): void {
            $conversationQuery->where(function (Builder $q) use ($userAId, $userBId): void {
                $q->where('user_one_id', $userAId)->where('user_two_id', $userBId);
            })->orWhere(function (Builder $q) use ($userAId, $userBId): void {
                $q->where('user_one_id', $userBId)->where('user_two_id', $userAId);
            });
        });
    }

    public function scopeInThread(Builder $query, User|int $user, User|int $otherUser): Builder
    {
        return $query
            ->select(['messages.*'])
            ->between($user, $otherUser);
    }

    public function scopeUnread(Builder $query, User|int|null $user = null): Builder
    {
        $query = $query
            ->select(['messages.*'])
            ->where(function (Builder $unreadQuery): void {
                $unreadQuery
                    ->where('is_read', false)
                    ->orWhereNull('read_at');
            });

        if ($user === null) {
            return $query;
        }

        $userId = $user instanceof User ? (int) $user->getKey() : (int) $user;

        return $query
            ->forUser($userId)
            ->where('sender_id', '!=', $userId);
    }

    protected function displayBody(): Attribute
    {
        return Attribute::get(function (): string {
            $body = preg_replace('/\s+/', ' ', (string) ($this->body ?? ''));

            return trim((string) $body);
        });
    }
}
