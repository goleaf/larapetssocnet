<?php

declare(strict_types=1);

namespace App\Support\Usernames;

use Illuminate\Support\Str;

final class UsernameNormalizer
{
    private function __construct() {}

    public static function normalize(?string $username): string
    {
        return (string) Str::of((string) $username)
            ->lower()
            ->replaceMatches(self::stripPattern(), '')
            ->trim('_-');
    }

    public static function generateBase(string $seed): string
    {
        return (string) Str::of($seed)
            ->lower()
            ->replaceMatches('/[^a-z0-9_-]+/', '_')
            ->trim('_-');
    }

    public static function stripPattern(): string
    {
        return (string) config('usernames.strip_pattern', '/[^a-z0-9_]/');
    }
}
