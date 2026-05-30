<?php

namespace App\Models\Content;

use App\Models\Identity\User;
use App\Models\Moderation\Report;
use App\Traits\HasCounterCache;
use App\Traits\HasReactions;
use Database\Factories\CommentFactory;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[UseFactory(CommentFactory::class)]
#[Appends([
    'excerpt',
])]
#[Fillable([
    'post_id',
    'user_id',
    'parent_id',
    'body',
    'body_html',
    'edited_at',
    'replies_count',
    'reactions_count',
    'paw_count',
    'love_count',
])]
class Comment extends Model
{
    use HasCounterCache;
    use HasFactory;
    use HasReactions;
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'edited_at' => 'datetime',
            'replies_count' => 'integer',
            'reactions_count' => 'integer',
            'paw_count' => 'integer',
            'love_count' => 'integer',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function reports(): MorphMany
    {
        return $this->morphMany(Report::class, 'reportable');
    }

    public function scopeTopLevel(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    public function scopeThreadColumns(Builder $query): Builder
    {
        return $query->select([
            'comments.id',
            'comments.post_id',
            'comments.user_id',
            'comments.parent_id',
            'comments.body',
            'comments.body_html',
            'comments.edited_at',
            'comments.replies_count',
            'comments.reactions_count',
            'comments.paw_count',
            'comments.love_count',
            'comments.created_at',
            'comments.updated_at',
            'comments.deleted_at',
        ]);
    }

    public function scopeVisibleTo(Builder $query, ?User $viewer): Builder
    {
        $query->whereHas('user', function (Builder $userQuery): void {
            $userQuery->where('is_banned', false);
        });

        if (! $viewer || ! User::hasBlocksTable()) {
            return $query;
        }

        $blockedIds = $viewer->blocking()
            ->pluck('blocked_id')
            ->merge($viewer->blockedBy()->pluck('blocker_id'))
            ->unique()
            ->values();

        if ($blockedIds->isNotEmpty()) {
            $query->whereNotIn('comments.user_id', $blockedIds);
        }

        return $query;
    }

    public function scopeRecent(Builder $query): Builder
    {
        return $query->latest();
    }

    public function isReply(): bool
    {
        return $this->parent_id !== null;
    }

    protected function excerpt(): Attribute
    {
        return Attribute::get(fn (): string => Str::limit(strip_tags((string) $this->body), 140));
    }
}
