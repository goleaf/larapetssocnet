<?php

namespace App\Models;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedPost extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'post_id',
        'user_id',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForUser(Builder $query, User $viewer): Builder
    {
        return $query->where('user_id', $viewer->getKey());
    }

    public function scopeWithVisiblePostForViewer(Builder $query, User $viewer): Builder
    {
        return $query->with([
            'post' => fn ($postQuery) => $postQuery
                ->with(['author', 'hashtags'])
                ->visibleTo($viewer)
                ->withListEngagement((int) $viewer->getKey()),
        ]);
    }

    public static function paginateForViewer(User $viewer, int $perPage = 15): LengthAwarePaginator
    {
        return self::query()
            ->forUser($viewer)
            ->withVisiblePostForViewer($viewer)
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }
}
