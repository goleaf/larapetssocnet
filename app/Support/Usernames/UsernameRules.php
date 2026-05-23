<?php

namespace App\Support\Usernames;

use App\Models\Identity\ReservedUsername;
use App\Models\Identity\User;
use App\Rules\ReservedUsernameRule;
use App\Rules\ValidUsernameRule;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class UsernameRules
{
    /**
     * @var list<string>|null
     */
    private static ?array $routeReservedCache = null;

    private function __construct() {}

    public static function minLength(): int
    {
        return (int) config('usernames.min_length', 3);
    }

    public static function maxLength(): int
    {
        return (int) config('usernames.max_length', 30);
    }

    public static function pattern(): string
    {
        return (string) config('usernames.pattern', '/^[a-z0-9_]+$/');
    }

    public static function disallowNumericOnly(): bool
    {
        return (bool) config('usernames.disallow_numeric_only', true);
    }

    /**
     * @return list<string>
     */
    public static function reservedList(): array
    {
        $configReserved = array_merge(
            self::configuredReservedValues(config('usernames.reserved', [])),
            self::configuredReservedValues(config('reserved_usernames', [])),
        );

        $normalized = [];
        foreach ($configReserved as $value) {
            array_push($normalized, ...self::normalizedReservedVariants($value));
        }

        return array_values(array_unique(array_filter($normalized, static fn (string $value): bool => $value !== '')));
    }

    /**
     * @return list<string>
     */
    public static function routeReservedList(): array
    {
        if (self::$routeReservedCache !== null) {
            return self::$routeReservedCache;
        }

        $segments = [];
        foreach (Route::getRoutes()->getRoutes() as $route) {
            $uri = (string) $route->uri();
            $first = trim(Str::before($uri, '/'));

            if ($first !== '' && ! str_contains($first, '{') && ! str_starts_with($first, '@')) {
                array_push($segments, ...self::normalizedReservedVariants($first));
            }

            $name = $route->getName();
            if (! is_string($name) || $name === '') {
                continue;
            }

            $routeNamePrefix = trim(Str::before($name, '.'));

            if ($routeNamePrefix !== '') {
                array_push($segments, ...self::normalizedReservedVariants($routeNamePrefix));
            }
        }

        self::$routeReservedCache = array_values(array_unique($segments));

        return self::$routeReservedCache;
    }

    public static function isReserved(string $username): bool
    {
        $normalized = UsernameNormalizer::normalize($username);

        if ($normalized === '') {
            return false;
        }

        if (in_array($normalized, self::reservedList(), true)) {
            return true;
        }

        if (in_array($normalized, self::routeReservedList(), true)) {
            return true;
        }

        return ReservedUsername::isReserved($normalized);
    }

    /**
     * @return list<string>
     */
    private static function configuredReservedValues(mixed $value): array
    {
        if (is_string($value)) {
            return [$value];
        }

        if (! is_array($value)) {
            return [];
        }

        $values = [];

        foreach ($value as $entry) {
            array_push($values, ...self::configuredReservedValues($entry));
        }

        return $values;
    }

    /**
     * @return list<string>
     */
    private static function normalizedReservedVariants(string $value): array
    {
        $trimmed = (string) Str::of($value)->lower()->trim();

        if ($trimmed === '') {
            return [];
        }

        $withUnderscores = (string) Str::of($trimmed)->replace(['-', '.', '/', ' '], '_');
        $withHyphens = (string) Str::of($trimmed)->replace(['_', '.', '/', ' '], '-');

        return array_values(array_unique(array_filter([
            UsernameNormalizer::normalize($trimmed),
            UsernameNormalizer::normalize($withUnderscores),
            UsernameNormalizer::normalize($withHyphens),
        ], static fn (string $normalized): bool => $normalized !== '')));
    }

    /**
     * @return array<int, mixed>
     */
    private static function baseRules(?int $ignoreUserId = null): array
    {
        $unique = Rule::unique(User::class, 'username');

        if ($ignoreUserId) {
            $unique->ignore($ignoreUserId);
        }

        return [
            'string',
            'min:'.self::minLength(),
            'max:'.self::maxLength(),
            'regex:'.self::pattern(),
            new ValidUsernameRule,
            new ReservedUsernameRule,
            $unique,
        ];
    }

    /**
     * @return array<int, mixed>
     */
    public static function requiredRules(?int $ignoreUserId = null): array
    {
        return array_merge(['required'], self::baseRules($ignoreUserId));
    }

    /**
     * @return array<int, mixed>
     */
    public static function optionalRules(?int $ignoreUserId = null): array
    {
        return array_merge(['nullable'], self::baseRules($ignoreUserId));
    }

    public static function isAvailable(string $username, ?int $ignoreUserId = null): bool
    {
        $normalized = UsernameNormalizer::normalize($username);

        $validator = Validator::make([
            'username' => $normalized,
        ], [
            'username' => self::requiredRules($ignoreUserId),
        ]);

        return ! $validator->fails();
    }

    public static function firstError(string $username, ?int $ignoreUserId = null): ?string
    {
        $normalized = UsernameNormalizer::normalize($username);

        $validator = Validator::make([
            'username' => $normalized,
        ], [
            'username' => self::requiredRules($ignoreUserId),
        ]);

        if (! $validator->fails()) {
            return null;
        }

        return $validator->errors()->first('username');
    }
}
