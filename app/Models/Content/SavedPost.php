<?php

namespace App\Models\Content;

use App\Models\Identity\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'post_id',
    'user_id',
])]
class SavedPost extends Model
{
    use HasFactory;

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class)->withTrashed();
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
                ->withTrashed()
                ->with(['author', 'hashtags'])
                ->where(function (Builder $visibilityQuery) use ($viewer): void {
                    $visibilityQuery->visibleTo($viewer);
                    $visibilityQuery->orWhereNotNull('posts.deleted_at');
                })
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
