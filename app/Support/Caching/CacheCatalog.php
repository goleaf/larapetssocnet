<?php

namespace App\Support\Caching;

use BadMethodCallException;
use DateTimeInterface;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

final class CacheCatalog
{
    /**
     * @var array<string, bool>
     */
    private static array $tagSupport = [];

    public static function key(string $group, string $resource, array $parts = []): string
    {
        $tenant = self::tenantSegment();
        $segments = array_filter([
            self::namespace(),
            self::version(),
            $tenant,
            $group,
            $resource,
            ...array_map(fn (string|int $part): string => (string) $part, $parts),
        ], fn (string $segment): bool => $segment !== '');

        return implode(':', $segments);
    }

    public static function ttl(string $name, int $fallback): int
    {
        return (int) config("caching.ttl.{$name}", $fallback);
    }

    public static function remember(
        string $key,
        DateTimeInterface|int $ttl,
        callable $callback,
        array $tags = [],
    ): mixed {
        if ($tags !== []) {
            return self::supportsTags()
                ? Cache::tags($tags)->remember($key, $ttl, $callback)
                : Cache::remember($key, $ttl, $callback);
        }

        return Cache::remember($key, $ttl, $callback);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::get($key, $default);
    }

    public static function forget(string $key, array $tags = []): void
    {
        if ($tags !== [] && self::supportsTags()) {
            Cache::tags($tags)->flush();

            return;
        }

        Cache::forget($key);
    }

    public static function rememberForever(string $key, callable $callback, array $tags = []): mixed
    {
        if ($tags !== []) {
            return self::supportsTags()
                ? Cache::tags($tags)->rememberForever($key, $callback)
                : Cache::rememberForever($key, $callback);
        }

        return Cache::rememberForever($key, $callback);
    }

    public static function touch(string $key, DateTimeInterface|int $ttl): bool
    {
        return Cache::touch($key, $ttl);
    }

    public static function withLock(string $lockKey, int $seconds, callable $callback)
    {
        if (! self::supportsLocks()) {
            return $callback();
        }

        $lock = Cache::lock($lockKey, $seconds);

        return $lock->block($seconds, $callback);
    }

    public static function supportsTags(): bool
    {
        if (! (bool) config('caching.tags.enabled_by_default', true)) {
            return false;
        }

        return self::supportsCacheFeature('tags');
    }

    public static function supportsLocks(): bool
    {
        return self::supportsCacheFeature('locks');
    }

    private static function supportsCacheFeature(string $feature): bool
    {
        if (! isset(self::$tagSupport[$feature])) {
            self::$tagSupport[$feature] = self::probeCacheFeature($feature);
        }

        return self::$tagSupport[$feature];
    }

    private static function probeCacheFeature(string $feature): bool
    {
        try {
            if ($feature === 'tags') {
                Cache::tags(['cache-catalog:probe'])->get('cache-catalog:probe');
            } else {
                Cache::lock('cache-catalog:lock-probe', 1)->block(0, fn (): bool => true);
            }

            return true;
        } catch (BadMethodCallException) {
            return false;
        }
    }

    private static function namespace(): string
    {
        return (string) config('caching.namespace', 'ps');
    }

    private static function version(): string
    {
        return (string) config('caching.version', 'v1');
    }

    /**
     * Keep hook for eventual tenant context support.
     */
    private static function tenantSegment(): string
    {
        return '';
    }

    private static function repository(): Repository
    {
        return Cache::store();
    }
}
