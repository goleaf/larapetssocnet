<?php

namespace App\Support\Profiles;

use Illuminate\Support\Str;

final class SocialLinkNormalizer
{
    /**
     * @var array<string, string>
     */
    private const LABELS = [
        'x' => 'Twitter/X',
        'instagram' => 'Instagram',
        'facebook' => 'Facebook',
        'youtube' => 'YouTube',
    ];

    /**
     * @var array<string, string>
     */
    private const HANDLE_HOSTS = [
        'x' => 'x.com',
        'instagram' => 'instagram.com',
    ];

    /**
     * @var array<string, string>
     */
    private const LEGACY_KEYS = [
        'twitter' => 'x',
    ];

    /**
     * @return array{x?: string, instagram?: string, facebook?: string, youtube?: string}
     */
    public static function editable(mixed $links): array
    {
        return self::normalizeInputs($links);
    }

    /**
     * @return array{x?: string, instagram?: string, facebook?: string, youtube?: string}
     */
    public static function normalizeInputs(mixed $links): array
    {
        if (! is_array($links)) {
            return [];
        }

        $links = self::withLegacyKeys($links);
        $normalized = [];

        foreach (array_keys(self::LABELS) as $platform) {
            $value = $links[$platform] ?? null;

            if (array_key_exists($platform, self::HANDLE_HOSTS)) {
                $handle = self::normalizeHandle($platform, $value);

                if ($handle !== null) {
                    $normalized[$platform] = $handle;
                }

                continue;
            }

            $url = self::normalizeUrl($value);

            if ($url !== null) {
                $normalized[$platform] = $url;
            }
        }

        return $normalized;
    }

    /**
     * @return array{x?: string, instagram?: string, facebook?: string, youtube?: string}|null
     */
    public static function forStorage(mixed $links): ?array
    {
        $inputs = self::normalizeInputs($links);
        $normalized = [];

        foreach ($inputs as $platform => $value) {
            if (array_key_exists($platform, self::HANDLE_HOSTS)) {
                $normalized[$platform] = self::profileUrl($platform, $value);

                continue;
            }

            $normalized[$platform] = $value;
        }

        return $normalized !== [] ? $normalized : null;
    }

    /**
     * @return list<array{key: string, label: string, url: string, display: string}>
     */
    public static function publicLinks(mixed $links): array
    {
        if (! is_array($links)) {
            return [];
        }

        $links = self::withLegacyKeys($links);
        $items = [];

        foreach (array_keys(self::LABELS) as $platform) {
            $value = $links[$platform] ?? null;
            $link = self::publicLink($platform, $value);

            if ($link === null) {
                continue;
            }

            $items[] = [
                'key' => $platform,
                'label' => self::LABELS[$platform],
                'url' => $link['url'],
                'display' => $link['display'],
            ];
        }

        return $items;
    }

    public static function normalizeHandle(string $platform, mixed $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $lowerValue = Str::lower($value);
        foreach (self::handleInputHosts($platform) as $host) {
            if (Str::startsWith($lowerValue, [$host.'/', 'www.'.$host.'/'])) {
                $value = 'https://'.$value;

                break;
            }
        }

        if (Str::startsWith(Str::lower($value), ['http://', 'https://'])) {
            $path = trim((string) parse_url($value, PHP_URL_PATH), '/');
            $value = Str::of($path)->before('/')->toString();
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        $value = ltrim($value, '@');

        return $value !== '' ? '@'.$value : null;
    }

    public static function normalizeUrl(mixed $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (! preg_match('/^https?:\/\//i', $value)) {
            $value = 'https://'.$value;
        }

        return $value;
    }

    public static function profileUrl(string $platform, string $handle): string
    {
        $handle = ltrim($handle, '@');
        $host = self::HANDLE_HOSTS[$platform] ?? '';

        return 'https://'.$host.'/'.$handle;
    }

    /**
     * @return array{url: string, display: string}|null
     */
    public static function publicLink(string $platform, mixed $value): ?array
    {
        if (array_key_exists($platform, self::HANDLE_HOSTS)) {
            $handle = self::normalizeHandle($platform, $value);

            if ($handle === null) {
                return null;
            }

            return [
                'url' => self::profileUrl($platform, $handle),
                'display' => $handle,
            ];
        }

        $url = self::normalizeUrl($value);

        if ($url === null || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        return [
            'url' => $url,
            'display' => Str::of($url)->replaceStart('https://', '')->replaceStart('http://', '')->trim('/')->toString(),
        ];
    }

    /**
     * @param  array<string, mixed>  $links
     * @return array<string, mixed>
     */
    private static function withLegacyKeys(array $links): array
    {
        foreach (self::LEGACY_KEYS as $legacy => $current) {
            if (! array_key_exists($current, $links) && array_key_exists($legacy, $links)) {
                $links[$current] = $links[$legacy];
            }
        }

        return $links;
    }

    /**
     * @return list<string>
     */
    private static function handleInputHosts(string $platform): array
    {
        return match ($platform) {
            'x' => ['x.com', 'twitter.com'],
            'instagram' => ['instagram.com'],
            default => [],
        };
    }
}
