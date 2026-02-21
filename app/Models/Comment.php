<?php

namespace App\Models;

use App\Traits\HasCounterCache;
use App\Traits\HasReactions;
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

class Comment extends Model
{
    use HasCounterCache;
    use HasFactory;
    use HasReactions;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'post_id',
        'user_id',
        'parent_id',
        'body',
        'status',
        'edited_at',
        'commentable_type',
        'commentable_id',
        'replies_count',
        'reactions_count',
    ];

    /**
     * @var list<string>
     */
    protected $appends = [
        'excerpt',
    ];

    protected function casts(): array
    {
        return [
            'edited_at' => 'datetime',
            'replies_count' => 'integer',
            'reactions_count' => 'integer',
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
