<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Like extends Model
{
    use HasFactory;

    protected $table = 'likes';

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'post_id',
        'created_at',
    ];

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
        $userId = $user instanceof User ? (int) $user->getKey() : (int) $user;

        return $query
            ->select(['likes.*'])
            ->where('likes.user_id', $userId);
    }

    public function scopeForModel(Builder $query, string $type, int|string $id): Builder
    {
        $normalized = strtolower($type);

        if (in_array($normalized, ['post', 'posts', strtolower(Post::class)], true)) {
            return $query
                ->select(['likes.*'])
                ->where('likes.post_id', $id);
        }

        return $query
            ->select(['likes.*'])
            ->whereKey(-1);
    }
}
