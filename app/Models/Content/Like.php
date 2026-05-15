<?php

namespace App\Models\Content;

use App\Models\Identity\User;
use Database\Factories\LikeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[UseFactory(LikeFactory::class)]
#[Fillable([
    'user_id',
    'post_id',
    'created_at',
])]
#[Table(name: 'likes')]
class Like extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function scopeByUser(Builder $query, User|int $user): Builder
    {
        $userId = $user instanceof User ? (int) $user->getKey() : $user;

        return $query
            ->select(['likes.*'])
            ->where('likes.user_id', $userId);
    }

    public function scopeForModel(Builder $query, string $type, int|string $id): Builder
    {
        $normalized = strtolower($type);

        if (in_array($normalized, ['post', 'posts', strtolower(Post::class), strtolower((new Post)->getMorphClass())], true)) {
            return $query
                ->select(['likes.*'])
                ->where('likes.post_id', $id);
        }

        return $query
            ->select(['likes.*'])
            ->whereKey(-1);
    }
}
