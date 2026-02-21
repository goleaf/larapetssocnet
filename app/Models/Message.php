<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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
        'sender_user_id',
        'recipient_user_id',
        'marketplace_listing_id',
        'body',
        'sent_at',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(MarketplaceListing::class, 'marketplace_listing_id');
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $subQuery) use ($user): void {
            $subQuery
                ->where('sender_user_id', $user->getKey())
                ->orWhere('recipient_user_id', $user->getKey());
        });
    }

    public function scopeBetween(Builder $query, User $firstUser, User $secondUser): Builder
    {
        return $query->where(function (Builder $conversationQuery) use ($firstUser, $secondUser): void {
            $conversationQuery
                ->where(function (Builder $subQuery) use ($firstUser, $secondUser): void {
                    $subQuery
                        ->where('sender_user_id', $firstUser->getKey())
                        ->where('recipient_user_id', $secondUser->getKey());
                })
                ->orWhere(function (Builder $subQuery) use ($firstUser, $secondUser): void {
                    $subQuery
                        ->where('sender_user_id', $secondUser->getKey())
                        ->where('recipient_user_id', $firstUser->getKey());
                });
        });
    }

    public function markAsRead(): bool
    {
        if ($this->read_at) {
            return false;
        }

        $this->forceFill([
            'read_at' => now(),
        ]);

        return $this->save();
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    public function isOwnedBy(User $user): bool
    {
        return (int) $this->sender_user_id === (int) $user->getKey();
    }
}
