<?php

namespace App\Models\Social;

use App\Models\Identity\User;
use Database\Factories\FollowFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[UseFactory(FollowFactory::class)]
#[Fillable([
    'follower_id',
    'following_id',
    'status',
    'created_at',
])]
#[Table(name: 'follows')]
class Follow extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function follower(): BelongsTo
    {
        return $this->belongsTo(User::class, 'follower_id');
    }

    public function following(): BelongsTo
    {
        return $this->belongsTo(User::class, 'following_id');
    }

    public function accept(): void
    {
        $this->update(['status' => 'accepted']);
    }

    public function reject(): void
    {
        $this->delete();
    }

    public function scopeAccepted(Builder $query): Builder
    {
        return $query->where('status', 'accepted');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeFollowers(Builder $query, User|int $user): Builder
    {
        $userId = $user instanceof User ? (int) $user->getKey() : $user;

        return $query
            ->select(['follows.*'])
            ->where('follows.following_id', $userId);
    }

    public function scopeFollowing(Builder $query, User|int $user): Builder
    {
        $userId = $user instanceof User ? (int) $user->getKey() : $user;

        return $query
            ->select(['follows.*'])
            ->where('follows.follower_id', $userId);
    }
}
