<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Reaction extends Model
{
    use HasFactory;

    public const TYPE_LOVE = 'love';

    public const TYPE_CUTE = 'cute';

    public const TYPE_FUNNY = 'funny';

    public const TYPE_WOW = 'wow';

    public const TYPE_SAD = 'sad';

    public const TYPE_SUPPORT = 'support';

    /**
     * @var list<string>
     */
    public const TYPES = [
        self::TYPE_LOVE,
        self::TYPE_CUTE,
        self::TYPE_FUNNY,
        self::TYPE_WOW,
        self::TYPE_SAD,
        self::TYPE_SUPPORT,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'type',
        'reactable_type',
        'reactable_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reactable(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeFromUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->getKey());
    }

    public function isType(string $type): bool
    {
        return $this->type === $type;
    }
}
