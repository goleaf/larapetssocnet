<?php

namespace App\Support\Posts;

final class PostMood
{
    public const Happy = 'happy';

    public const Excited = 'excited';

    public const Proud = 'proud';

    public const Worried = 'worried';

    public const Sad = 'sad';

    public const Grateful = 'grateful';

    public const Playful = 'playful';

    /**
     * @var array<string, array{label: string, emoji: string}>
     */
    public const DISPLAY = [
        self::Happy => ['label' => 'Happy', 'emoji' => '😊'],
        self::Excited => ['label' => 'Excited', 'emoji' => '🎉'],
        self::Proud => ['label' => 'Proud', 'emoji' => '🌟'],
        self::Worried => ['label' => 'Worried', 'emoji' => '😟'],
        self::Sad => ['label' => 'Sad', 'emoji' => '😢'],
        self::Grateful => ['label' => 'Grateful', 'emoji' => '🙏'],
        self::Playful => ['label' => 'Playful', 'emoji' => '🐾'],
    ];

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_keys(self::DISPLAY);
    }

    public static function normalize(mixed $value): ?string
    {
        $normalized = strtolower(trim((string) $value));

        if ($normalized === '' || $normalized === 'none') {
            return null;
        }

        return in_array($normalized, self::values(), true) ? $normalized : null;
    }

    public static function label(?string $value): ?string
    {
        $normalized = self::normalize($value);

        return $normalized ? self::DISPLAY[$normalized]['label'] : null;
    }

    public static function emoji(?string $value): ?string
    {
        $normalized = self::normalize($value);

        return $normalized ? self::DISPLAY[$normalized]['emoji'] : null;
    }
}
