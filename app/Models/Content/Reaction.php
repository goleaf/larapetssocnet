<?php

namespace App\Models\Content;

use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'user_id',
    'type',
    'reactable_type',
    'reactable_id',
])]
class Reaction extends Model
{
    use HasFactory;

    public const TYPE_LOVE = 'love';

    public const TYPE_CUTE = 'cute';

    public const TYPE_FUNNY = 'funny';

    public const TYPE_WOW = 'wow';

    public const TYPE_SAD = 'sad';

    public const TYPE_SUPPORT = 'support';

    public const TYPE_LIKE = 'like';

    public const TYPE_LAUGH = 'laugh';

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
     * @var array<string, string>
     */
    public const TYPE_ALIASES = [
        self::TYPE_LIKE => self::TYPE_LOVE,
        self::TYPE_LAUGH => self::TYPE_FUNNY,
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
        return $this->type === static::normalizeType($type);
    }

    /**
     * @return list<string>
     */
    public static function allowedTypes(): array
    {
        return array_values(array_unique([
            ...self::TYPES,
            ...array_keys(self::TYPE_ALIASES),
        ]));
    }

    public static function normalizeType(string $type): string
    {
        $normalized = strtolower($type);

        return self::TYPE_ALIASES[$normalized] ?? $normalized;
    }

    /**
     * @return array<string, string>
     */
    public static function emojiMap(): array
    {
        return [
            self::TYPE_LOVE => '❤️',
            self::TYPE_CUTE => '🥹',
            self::TYPE_FUNNY => '😂',
            self::TYPE_WOW => '😮',
            self::TYPE_SAD => '😢',
            self::TYPE_SUPPORT => '🤝',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function labelMap(): array
    {
        return [
            self::TYPE_LOVE => 'Love',
            self::TYPE_CUTE => 'Cute',
            self::TYPE_FUNNY => 'Funny',
            self::TYPE_WOW => 'Wow',
            self::TYPE_SAD => 'Sad',
            self::TYPE_SUPPORT => 'Support',
        ];
    }
}
