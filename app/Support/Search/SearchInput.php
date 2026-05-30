<?php

namespace App\Support\Search;

use Illuminate\Support\Str;

final class SearchInput
{
    public const int MIN_LENGTH = 2;

    public const int MAX_LENGTH = 80;

    public static function normalize(mixed $value, int $maxLength = self::MAX_LENGTH): string
    {
        return Str::of((string) $value)
            ->replaceMatches('/[\x00-\x1F\x7F%_\\\\]+/u', ' ')
            ->squish()
            ->limit($maxLength, '')
            ->trim()
            ->toString();
    }

    public static function hasSearchableLength(mixed $value): bool
    {
        return mb_strlen(self::normalize($value)) >= self::MIN_LENGTH;
    }

    public static function containsPattern(mixed $value): string
    {
        return '%'.self::normalize($value).'%';
    }

    public static function prefixPattern(mixed $value): string
    {
        return self::normalize($value).'%';
    }
}
