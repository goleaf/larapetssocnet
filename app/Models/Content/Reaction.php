<?php

namespace App\Models\Content;

use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

#[Fillable([
    'user_id',
    'type',
    'reactable_type',
    'reactable_id',
])]
class Reaction extends Model
{
    use HasFactory;

    public const TYPE_PAW = 'paw';

    public const TYPE_LOVE = 'love';

    public const TYPE_HAHA = 'haha';

    public const TYPE_WOW = 'wow';

    public const TYPE_SAD = 'sad';

    public const TYPE_ANGRY = 'angry';

    public const TYPE_CUTE = 'cute';

    public const TYPE_FUNNY = 'funny';

    public const TYPE_SUPPORT = 'support';

    public const TYPE_LIKE = 'like';

    public const TYPE_LAUGH = 'laugh';

    public const TYPE_CARE = 'care';

    /**
     * @var list<string>
     */
    public const TYPES = [
        self::TYPE_PAW,
        self::TYPE_LOVE,
        self::TYPE_HAHA,
        self::TYPE_WOW,
        self::TYPE_SAD,
        self::TYPE_ANGRY,
    ];

    /**
     * @var array<string, string>
     */
    public const TYPE_ALIASES = [
        self::TYPE_LIKE => self::TYPE_PAW,
        self::TYPE_CUTE => self::TYPE_PAW,
        self::TYPE_SUPPORT => self::TYPE_PAW,
        self::TYPE_CARE => self::TYPE_LOVE,
        self::TYPE_LAUGH => self::TYPE_HAHA,
        self::TYPE_FUNNY => self::TYPE_HAHA,
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
        return $query->where('type', static::normalizeType($type));
    }

    public function scopeFromUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->getKey());
    }

    public function isType(string $type): bool
    {
        return static::normalizeType((string) $this->type) === static::normalizeType($type);
    }

    /**
     * @return list<string>
     */
    public static function allowedTypes(): array
    {
        return array_values(array_unique([
            ...static::types(),
            ...array_keys(static::aliases()),
        ]));
    }

    /**
     * @return list<string>
     */
    public static function allowedCommentTypes(): array
    {
        $commentTypes = static::commentTypes();
        $commentAliases = collect(static::aliases())
            ->filter(fn (string $target): bool => in_array(static::normalizeType($target), $commentTypes, true))
            ->keys()
            ->all();

        return array_values(array_unique([
            ...$commentTypes,
            ...$commentAliases,
        ]));
    }

    public static function normalizeType(string $type): string
    {
        $normalized = Str::lower(trim($type));

        return static::aliases()[$normalized] ?? $normalized;
    }

    /**
     * @return array<string, array{label: string, emoji: string, color: string, counter_column: string, button_class: string, icon_class: string}>
     */
    public static function definitions(): array
    {
        /** @var array<string, array{label: string, emoji: string, color: string, counter_column: string, button_class: string, icon_class: string}> $definitions */
        $definitions = config('reactions.types', []);

        return $definitions;
    }

    /**
     * @return list<string>
     */
    public static function types(): array
    {
        $configuredTypes = array_keys(static::definitions());

        return $configuredTypes === [] ? self::TYPES : array_values($configuredTypes);
    }

    public static function defaultType(): string
    {
        $default = (string) config('reactions.default', self::TYPE_PAW);
        $normalized = static::normalizeType($default);

        return in_array($normalized, static::types(), true) ? $normalized : self::TYPE_PAW;
    }

    /**
     * @return list<string>
     */
    public static function commentTypes(): array
    {
        /** @var list<string> $types */
        $types = config('reactions.comment_types', [self::TYPE_PAW, self::TYPE_LOVE]);

        return collect($types)
            ->map(fn (string $type): string => static::normalizeType($type))
            ->filter(fn (string $type): bool => in_array($type, static::types(), true))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public static function aliases(): array
    {
        /** @var array<string, string> $configuredAliases */
        $configuredAliases = config('reactions.aliases', []);

        return [
            ...self::TYPE_ALIASES,
            ...$configuredAliases,
        ];
    }

    public static function counterColumn(string $type): string
    {
        $normalized = static::normalizeType($type);
        $definition = static::definitions()[$normalized] ?? null;

        return is_array($definition) ? (string) $definition['counter_column'] : $normalized.'_count';
    }

    /**
     * @return array<string, string>
     */
    public static function emojiMap(): array
    {
        return collect(static::definitions())
            ->mapWithKeys(fn (array $definition, string $type): array => [$type => (string) $definition['emoji']])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public static function labelMap(): array
    {
        return collect(static::definitions())
            ->mapWithKeys(fn (array $definition, string $type): array => [$type => (string) $definition['label']])
            ->all();
    }

    /**
     * @return list<array{type: string, label: string, emoji: string, color: string, counter_column: string, button_class: string, icon_class: string}>
     */
    public static function options(): array
    {
        return collect(static::definitions())
            ->map(fn (array $definition, string $type): array => [
                'type' => $type,
                'label' => (string) $definition['label'],
                'emoji' => (string) $definition['emoji'],
                'color' => (string) $definition['color'],
                'counter_column' => (string) $definition['counter_column'],
                'button_class' => (string) $definition['button_class'],
                'icon_class' => (string) $definition['icon_class'],
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, int>
     */
    public static function countMapForModel(EloquentModel $model): array
    {
        $counts = [];

        foreach (static::types() as $type) {
            $counts[$type] = (int) ($model->getAttribute(static::counterColumn($type)) ?? 0);
        }

        return $counts;
    }

    /**
     * @return list<array{type: string, label: string, emoji: string, count: int, icon_class: string}>
     */
    public static function topCountsForModel(EloquentModel $model, int $limit = 3): array
    {
        $labels = static::labelMap();
        $emojis = static::emojiMap();
        $definitions = static::definitions();

        return collect(static::countMapForModel($model))
            ->filter(fn (int $count): bool => $count > 0)
            ->sortDesc()
            ->take($limit)
            ->map(fn (int $count, string $type): array => [
                'type' => $type,
                'label' => $labels[$type] ?? Str::headline($type),
                'emoji' => $emojis[$type] ?? '',
                'count' => $count,
                'icon_class' => (string) ($definitions[$type]['icon_class'] ?? 'bg-cream text-fur'),
            ])
            ->values()
            ->all();
    }
}
